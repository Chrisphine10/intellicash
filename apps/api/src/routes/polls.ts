import { Router } from "express";
import { z } from "zod";
import { Prisma } from "@prisma/client";
import { appendAuditEvent } from "../services/audit-service";
import { requireAuth, type AuthenticatedUser } from "../middleware/auth";
import { scopeGroupWhere } from "../services/account-scope";
import { signLedgerEntry } from "../domain/ledger";
import { ApiHttpError, ok } from "../lib/http";
import { prisma } from "../lib/prisma";

/**
 * Voting / polls — the group's democratic decisions, taken in a meeting.
 *
 * Two shapes share one model:
 *   ROLE_ELECTION — members stand for an office (Chairperson, Secretary, …)
 *                   and the group elects one of them.
 *   DECISION      — a motion put to the group ("Should we buy a water tank?"),
 *                   usually Yes / No but any set of options is allowed.
 *
 * A poll is live scaffolding: the tally moves as votes come in. Closing it
 * freezes the result AND writes a row into the minute-book `Vote` model, so
 * the immutable, hash-signed audit trail keeps its single source of truth for
 * resolutions — polls are the ballot box, `Vote` is the minute.
 *
 * Uses the pre-existing `votes:read` / `votes:write` permissions (GROUP_ACCOUNT
 * and MOBILE_CORE keys carry both), so template role rows already in the DB
 * pick this module up with no migration.
 */
const router = Router();

const pollTypes = ["ROLE_ELECTION", "DECISION"] as const;

/** Offices a group elects. Mirrors `memberRoles` minus the non-elected ones. */
const electableRoles = ["CHAIRPERSON", "SECRETARY", "TREASURER", "MONEY_COUNTER", "KEY_HOLDER", "MEMBER"] as const;

const optionSchema = z
  .object({
    label: z.string().trim().min(1).max(120).optional(),
    memberId: z.string().trim().min(1).nullish()
  })
  .refine((option) => Boolean(option.label || option.memberId), {
    message: "An option needs a label or a member."
  });

const pollCreateSchema = z.object({
  type: z.enum(pollTypes).default("DECISION"),
  title: z.string().trim().min(3).max(200),
  description: z.string().trim().max(1000).nullish(),
  targetRole: z.enum(electableRoles).nullish(),
  meetingId: z.string().trim().min(1).nullish(),
  secretBallot: z.boolean().default(false),
  closesAt: z.coerce.date().nullish(),
  options: z.array(optionSchema).min(2).max(30)
});

const castVoteSchema = z.object({
  optionId: z.string().trim().min(1),
  memberId: z.string().trim().min(1).optional()
});

function routeParam(value: string | string[] | undefined, name: string) {
  if (typeof value === "string" && value.trim()) return value;
  throw new ApiHttpError(400, "INVALID_ROUTE_PARAM", `Missing route parameter: ${name}.`);
}

/** Title-cases an office code for human copy: `CHAIRPERSON` -> `Chairperson`. */
function roleLabel(role: string) {
  return role
    .split("_")
    .map((word) => (word ? word[0] + word.slice(1).toLowerCase() : word))
    .join(" ");
}

/**
 * The member whose ballot the caller can see. A GROUP_ACCOUNT tablet has no
 * member identity of its own, so it sees only the public tally.
 */
function viewerMemberId(user: AuthenticatedUser | undefined) {
  return user?.memberId ?? null;
}

function pollInclude(memberId: string | null) {
  return {
    meeting: { select: { id: true, title: true, status: true } },
    options: {
      orderBy: { position: "asc" as const },
      include: {
        member: { select: { id: true, fullName: true, role: true } },
        _count: { select: { votes: true } }
      }
    },
    // Scoped to the caller so we can answer "have I voted?" without ever
    // shipping the whole ballot list to the client.
    votes: {
      where: { memberId: memberId ?? "__no_member__" },
      select: { optionId: true }
    },
    _count: { select: { votes: true } }
  } satisfies Prisma.PollInclude;
}

type PollWithRelations = Prisma.PollGetPayload<{ include: ReturnType<typeof pollInclude> }>;

/**
 * Public shape of a poll. Counts are plain integers. For a secret ballot the
 * caller's own choice is withheld — only `hasVoted` leaks, which it must, or
 * the app cannot tell someone they have already voted.
 */
function serializePoll(poll: PollWithRelations) {
  const myOptionId = poll.votes[0]?.optionId ?? null;
  return {
    id: poll.id,
    groupId: poll.groupId,
    meetingId: poll.meetingId,
    type: poll.type,
    title: poll.title,
    description: poll.description,
    targetRole: poll.targetRole,
    status: poll.status,
    secretBallot: poll.secretBallot,
    createdByUserId: poll.createdByUserId,
    closesAt: poll.closesAt,
    closedAt: poll.closedAt,
    resultSummary: poll.resultSummary,
    createdAt: poll.createdAt,
    updatedAt: poll.updatedAt,
    meeting: poll.meeting,
    options: poll.options.map((option) => ({
      id: option.id,
      label: option.label,
      memberId: option.memberId,
      position: option.position,
      member: option.member,
      voteCount: option._count.votes
    })),
    totalVotes: poll._count.votes,
    myVote: poll.secretBallot ? null : myOptionId,
    hasVoted: myOptionId !== null
  };
}

/** Loads a poll the caller is allowed to see, or 404s. */
async function loadPoll(user: AuthenticatedUser | undefined, pollId: string) {
  const poll = await prisma.poll.findFirst({
    where: { id: pollId, group: scopeGroupWhere(user, {}) },
    include: pollInclude(viewerMemberId(user))
  });
  if (!poll) {
    throw new ApiHttpError(404, "POLL_NOT_FOUND", "Vote not found or outside this account.");
  }
  return poll;
}

/** A poll stops accepting ballots when closed, or once `closesAt` has passed. */
function assertPollAcceptsVotes(poll: { status: string; closesAt: Date | null }) {
  if (poll.status !== "OPEN") {
    throw new ApiHttpError(409, "POLL_CLOSED", "This vote is closed. No more votes can be cast.");
  }
  if (poll.closesAt && poll.closesAt.getTime() <= Date.now()) {
    throw new ApiHttpError(409, "POLL_CLOSED", "This vote has already reached its closing time.");
  }
}

/** POST /groups/:id/polls — open a new vote for the group. */
router.post("/groups/:id/polls", requireAuth("votes:write"), async (req, res, next) => {
  try {
    const groupId = routeParam(req.params.id, "id");
    const body = pollCreateSchema.parse(req.body);

    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }

    if (body.type === "ROLE_ELECTION" && !body.targetRole) {
      throw new ApiHttpError(400, "ROLE_REQUIRED", "An election must say which position is being filled.");
    }

    if (body.meetingId) {
      const meeting = await prisma.meeting.findFirst({
        where: { id: body.meetingId, groupId: group.id },
        select: { id: true }
      });
      if (!meeting) {
        throw new ApiHttpError(404, "MEETING_NOT_FOUND", "Meeting does not exist in this group.");
      }
    }

    // Every candidate must be an active member of this group — an election
    // cannot put an outsider (or someone else's member) on the ballot.
    const memberIds = body.options
      .map((option) => option.memberId)
      .filter((id): id is string => Boolean(id));
    const members = memberIds.length
      ? await prisma.member.findMany({
          where: { id: { in: memberIds }, groupId: group.id, status: "ACTIVE" },
          select: { id: true, fullName: true }
        })
      : [];
    const memberById = new Map(members.map((member) => [member.id, member]));
    for (const id of memberIds) {
      if (!memberById.has(id)) {
        throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "A candidate is not an active member of this group.");
      }
    }
    if (new Set(memberIds).size !== memberIds.length) {
      throw new ApiHttpError(400, "DUPLICATE_CANDIDATE", "The same member cannot stand twice in one vote.");
    }

    const created = await prisma.poll.create({
      data: {
        groupId: group.id,
        meetingId: body.meetingId ?? null,
        type: body.type,
        title: body.title,
        description: body.description ?? null,
        targetRole: body.targetRole ?? null,
        secretBallot: body.secretBallot,
        closesAt: body.closesAt ?? null,
        createdByUserId: req.user?.id ?? null,
        options: {
          create: body.options.map((option, index) => ({
            // A candidate row falls back to the member's own name.
            label: option.label ?? memberById.get(option.memberId ?? "")?.fullName ?? `Option ${index + 1}`,
            memberId: option.memberId ?? null,
            position: index
          }))
        }
      },
      include: pollInclude(viewerMemberId(req.user))
    });

    const poll = serializePoll(created);

    await appendAuditEvent({
      actorUserId: req.user?.id,
      entityType: "POLL",
      entityId: poll.id,
      type: "POLL_CREATED",
      payload: {
        pollId: poll.id,
        groupId: poll.groupId,
        meetingId: poll.meetingId,
        type: poll.type,
        title: poll.title,
        targetRole: poll.targetRole,
        secretBallot: poll.secretBallot,
        options: poll.options.map((option) => ({ id: option.id, label: option.label, memberId: option.memberId }))
      }
    });

    ok(res.status(201), poll);
  } catch (error) {
    next(error);
  }
});

/** GET /groups/:id/polls — every vote the group has run, newest first. */
router.get("/groups/:id/polls", requireAuth("votes:read"), async (req, res, next) => {
  try {
    const groupId = routeParam(req.params.id, "id");
    const group = await prisma.group.findFirst({
      where: scopeGroupWhere(req.user, { id: groupId }),
      select: { id: true }
    });
    if (!group) {
      throw new ApiHttpError(404, "GROUP_NOT_FOUND", "Group does not exist or is outside this account.");
    }

    const status = typeof req.query.status === "string" ? req.query.status.toUpperCase() : undefined;
    const polls = await prisma.poll.findMany({
      where: {
        groupId: group.id,
        ...(status === "OPEN" || status === "CLOSED" ? { status } : {}),
        ...(typeof req.query.meetingId === "string" ? { meetingId: req.query.meetingId } : {})
      },
      include: pollInclude(viewerMemberId(req.user)),
      orderBy: [{ status: "asc" }, { createdAt: "desc" }],
      take: 200
    });

    ok(res, polls.map(serializePoll));
  } catch (error) {
    next(error);
  }
});

/** GET /polls/:pollId — one vote, with its live tally. */
router.get("/polls/:pollId", requireAuth("votes:read"), async (req, res, next) => {
  try {
    const poll = await loadPoll(req.user, routeParam(req.params.pollId, "pollId"));
    ok(res, serializePoll(poll));
  } catch (error) {
    next(error);
  }
});

/**
 * POST /polls/:pollId/vote — cast one ballot.
 *
 * A MEMBER-role caller always votes as themselves. A GROUP_ACCOUNT (the shared
 * tablet at the meeting table) records the ballot on behalf of a member who is
 * present, and must name them.
 */
router.post("/polls/:pollId/vote", requireAuth("votes:write"), async (req, res, next) => {
  try {
    const pollId = routeParam(req.params.pollId, "pollId");
    const body = castVoteSchema.parse(req.body);
    const user = req.user;

    const poll = await prisma.poll.findFirst({
      where: { id: pollId, group: scopeGroupWhere(user, {}) },
      select: {
        id: true,
        groupId: true,
        status: true,
        closesAt: true,
        secretBallot: true,
        title: true,
        options: { select: { id: true, label: true } }
      }
    });
    if (!poll) {
      throw new ApiHttpError(404, "POLL_NOT_FOUND", "Vote not found or outside this account.");
    }

    assertPollAcceptsVotes(poll);

    const option = poll.options.find((candidate) => candidate.id === body.optionId);
    if (!option) {
      throw new ApiHttpError(400, "OPTION_NOT_IN_POLL", "That choice does not belong to this vote.");
    }

    // A member votes for themselves; anyone else must name the member.
    const memberId = user?.role === "MEMBER" ? user.memberId : (body.memberId ?? user?.memberId ?? null);
    if (!memberId) {
      throw new ApiHttpError(400, "MEMBER_REQUIRED", "Say which member is casting this vote.");
    }

    const member = await prisma.member.findFirst({
      where: { id: memberId, groupId: poll.groupId, status: "ACTIVE" },
      select: { id: true, fullName: true }
    });
    if (!member) {
      throw new ApiHttpError(404, "MEMBER_NOT_FOUND", "That member is not active in this group.");
    }

    try {
      await prisma.pollVote.create({
        data: { pollId: poll.id, optionId: option.id, memberId: member.id }
      });
    } catch (error) {
      // The `@@unique([pollId, memberId])` constraint is the real guard —
      // a read-then-write check would race two tablets voting at once.
      if (error instanceof Prisma.PrismaClientKnownRequestError && error.code === "P2002") {
        throw new ApiHttpError(409, "ALREADY_VOTED", `${member.fullName} has already voted in this vote.`);
      }
      throw error;
    }

    await appendAuditEvent({
      actorUserId: user?.id,
      entityType: "POLL",
      entityId: poll.id,
      type: "POLL_VOTE_CAST",
      payload: {
        pollId: poll.id,
        groupId: poll.groupId,
        memberId: member.id,
        // A secret ballot records THAT a vote happened, never for whom.
        optionId: poll.secretBallot ? null : option.id,
        secretBallot: poll.secretBallot
      }
    });

    ok(res.status(201), serializePoll(await loadPoll(user, poll.id)));
  } catch (error) {
    next(error);
  }
});

/**
 * Human sentence for the frozen result. A tie is reported as a tie — the
 * system never breaks it; the group must re-run the vote.
 */
function buildResultSummary(input: {
  type: string;
  title: string;
  targetRole: string | null;
  tally: { label: string; voteCount: number }[];
  totalVotes: number;
}) {
  const { type, title, targetRole, tally, totalVotes } = input;
  if (totalVotes === 0) {
    return type === "ROLE_ELECTION"
      ? `No votes were cast for ${targetRole ? roleLabel(targetRole) : "this position"}, so no one was elected.`
      : `No votes were cast on "${title}", so nothing was decided.`;
  }

  const sorted = [...tally].sort((a, b) => b.voteCount - a.voteCount);
  const top = sorted[0]!.voteCount;
  const leaders = sorted.filter((option) => option.voteCount === top);

  if (leaders.length > 1) {
    const names = leaders.map((option) => option.label).join(" and ");
    return type === "ROLE_ELECTION"
      ? `Tie between ${names} — ${top} ${top === 1 ? "vote" : "votes"} each. No one was elected for ` +
          `${targetRole ? roleLabel(targetRole) : "this position"}; the group must vote again.`
      : `Tie on "${title}" — ${names} each got ${top} ${top === 1 ? "vote" : "votes"}. Nothing was decided; ` +
          "the group must vote again.";
  }

  const winner = leaders[0]!;
  if (type === "ROLE_ELECTION") {
    return `${winner.label} elected ${targetRole ? roleLabel(targetRole) : "office holder"} with ` +
      `${winner.voteCount} of ${totalVotes} votes`;
  }

  // Yes / No motions read like minutes; anything else names the winning choice.
  const yes = tally.find((option) => /^y(es)?$/i.test(option.label.trim()));
  const no = tally.find((option) => /^n(o)?$/i.test(option.label.trim()));
  if (yes && no) {
    const passed = yes.voteCount > no.voteCount;
    return `Motion ${passed ? "passed" : "rejected"}: ${yes.voteCount} yes, ${no.voteCount} no`;
  }
  return `"${winner.label}" chosen with ${winner.voteCount} of ${totalVotes} votes`;
}

/**
 * POST /polls/:pollId/close — freeze the vote.
 *
 * Sets the status, writes the human result sentence, and mirrors the outcome
 * into the hash-signed `Vote` minute-book so the resolution lands in the audit
 * trail alongside every other group resolution.
 */
router.post("/polls/:pollId/close", requireAuth("votes:write"), async (req, res, next) => {
  try {
    const pollId = routeParam(req.params.pollId, "pollId");
    const user = req.user;

    const existing = await prisma.poll.findFirst({
      where: { id: pollId, group: scopeGroupWhere(user, {}) },
      include: {
        options: {
          orderBy: { position: "asc" },
          select: { id: true, label: true, memberId: true, _count: { select: { votes: true } } }
        },
        _count: { select: { votes: true } }
      }
    });
    if (!existing) {
      throw new ApiHttpError(404, "POLL_NOT_FOUND", "Vote not found or outside this account.");
    }
    if (existing.status === "CLOSED") {
      throw new ApiHttpError(409, "ALREADY_CLOSED", "This vote is already closed.");
    }

    const tally = existing.options.map((option) => ({
      id: option.id,
      label: option.label,
      memberId: option.memberId,
      voteCount: option._count.votes
    }));
    const totalVotes = existing._count.votes;
    const resultSummary = buildResultSummary({
      type: existing.type,
      title: existing.title,
      targetRole: existing.targetRole,
      tally,
      totalVotes
    });

    const totalEligible = await prisma.member.count({
      where: { groupId: existing.groupId, status: "ACTIVE" }
    });

    // Map the poll onto the minute-book's yes/no/abstain vocabulary: the
    // leading choice is the "yes", everything else is a "no", and eligible
    // members who never voted are abstentions.
    const sorted = [...tally].sort((a, b) => b.voteCount - a.voteCount);
    const top = sorted[0]?.voteCount ?? 0;
    const tied = totalVotes > 0 && sorted.filter((option) => option.voteCount === top).length > 1;
    const yesOption = tally.find((option) => /^y(es)?$/i.test(option.label.trim()));
    const noOption = tally.find((option) => /^n(o)?$/i.test(option.label.trim()));
    const yesCount = yesOption && noOption ? yesOption.voteCount : top;
    const noCount = Math.max(totalVotes - yesCount, 0);
    const result = totalVotes === 0 ? "DEFERRED" : tied ? "TIED" : yesCount > noCount ? "PASSED" : "FAILED";

    const { poll, vote } = await prisma.$transaction(async (tx) => {
      const updated = await tx.poll.update({
        where: { id: existing.id },
        data: { status: "CLOSED", closedAt: new Date(), resultSummary },
        include: pollInclude(viewerMemberId(user))
      });

      const hashPayload = {
        groupId: existing.groupId,
        pollId: existing.id,
        resolutionType: existing.type === "ROLE_ELECTION" ? "OFFICER_ELECTION" : "MINUTES_APPROVAL",
        motion: existing.title,
        result,
        yesCount,
        noCount,
        totalVotes,
        totalEligible: Math.max(totalEligible, 1)
      };

      const minute = await tx.vote.create({
        data: {
          groupId: existing.groupId,
          meetingId: existing.meetingId,
          resolutionType: existing.type === "ROLE_ELECTION" ? "OFFICER_ELECTION" : "MINUTES_APPROVAL",
          motion: existing.title,
          result,
          quorumRequired: Math.ceil(Math.max(totalEligible, 1) / 2),
          yesCount,
          noCount,
          abstainCount: Math.max(Math.max(totalEligible, 1) - totalVotes, 0),
          totalEligible: Math.max(totalEligible, 1),
          hash: signLedgerEntry(hashPayload)
        }
      });

      return { poll: updated, vote: minute };
    });

    const serialized = serializePoll(poll);

    await appendAuditEvent({
      actorUserId: user?.id,
      entityType: "POLL",
      entityId: serialized.id,
      type: "POLL_CLOSED",
      payload: {
        pollId: serialized.id,
        groupId: serialized.groupId,
        voteId: vote.id,
        result,
        resultSummary,
        totalVotes,
        tally: tally.map((option) => ({ label: option.label, voteCount: option.voteCount }))
      }
    });

    ok(res, { ...serialized, voteId: vote.id, result });
  } catch (error) {
    next(error);
  }
});

export { router as pollsRouter };
