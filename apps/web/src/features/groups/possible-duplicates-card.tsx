"use client";

import Link from "next/link";
import { useEffect, useState } from "react";
import { apiFetch, formatDate } from "../../lib/api";

interface DuplicateGroup {
  id: string;
  name: string;
  code: string;
  county: string;
  sourceSystem: string | null;
  createdAt: string;
  _count: { members: number; meetings: number; ledgerEntries: number; visits: number };
}

interface DuplicatesResponse {
  threshold: number;
  pairs: Array<{ similarity: number; sameKey: boolean; groups: DuplicateGroup[] }>;
}

const plural = (n: number, one: string, many = `${one}s`) => `${n} ${n === 1 ? one : many}`;

const SOURCE: Record<string, string> = {
  FLOURISH_ONBOARDING_2026: "FLOURISH register",
  MOBILE_SELF_SIGNUP: "Signed up on a phone",
  AUTO_FOR_UNLINKED_LOGIN: "Created for an unlinked login"
};

/**
 * Groups that look like the same group registered twice, for an admin to
 * check. Advisory: two groups can share most of a name ("Kariguri Wendani
 * Women", "Kiamuvia Wendani Women"). What each record holds is shown, because
 * that — not the name — decides which one is kept.
 */
export function PossibleDuplicatesCard() {
  const [data, setData] = useState<DuplicatesResponse | null>(null);

  useEffect(() => {
    let live = true;
    apiFetch<DuplicatesResponse>("/group-duplicates")
      .then((response) => live && setData(response))
      .catch(() => undefined);
    return () => {
      live = false;
    };
  }, []);

  if (!Array.isArray(data?.pairs) || data.pairs.length === 0) return null;

  return (
    <section className="data-card">
      <header>
        <div>
          <h3>Possible duplicate groups ({data.pairs.length})</h3>
          <p>
            Similar names, compared across all counties. Check each pair: the same group is merged into the record that
            holds its members and books; different groups are left as they are.
          </p>
        </div>
      </header>
      <div className="table-scroll">
        <table className="data-table">
          <thead>
            <tr>
              <th>Match</th>
              <th>Group</th>
              <th>Group</th>
            </tr>
          </thead>
          <tbody>
            {data.pairs.map((pair) => (
              <tr key={pair.groups.map((group) => group.id).join("-")}>
                <td>{pair.sameKey ? "Same name" : `${Math.round(pair.similarity * 100)}% of words`}</td>
                {pair.groups.map((group) => (
                  <td key={group.id}>
                    <Link href={`/dashboard/groups/${group.id}`}>
                      <strong>{group.name}</strong>
                    </Link>
                    <br />
                    <small>
                      {group.code} · {group.county} · {SOURCE[group.sourceSystem ?? ""] ?? group.sourceSystem ?? "Console"} ·{" "}
                      {formatDate(group.createdAt)}
                      <br />
                      {plural(group._count.members, "member")} · {plural(group._count.meetings, "meeting")} ·{" "}
                      {plural(group._count.ledgerEntries, "ledger entry", "ledger entries")} · {plural(group._count.visits, "visit")}
                    </small>
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </section>
  );
}
