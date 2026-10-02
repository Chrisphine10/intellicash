/**
 * The Kobo "VSLA Group Profile & Needs Assessment" form (IWL-VSLA-01), read
 * into what IntelliCash stores about a group's baseline.
 *
 * Pure: no database. It parses the export, types the figures, checks them,
 * and maps the handful of answers that mean the same thing as a scorecard
 * question. The import script decides which group a submission belongs to and
 * writes it; this file only says what the submission says.
 *
 * Two rules run through it:
 * - Reported figures are the group's own account on the day, typed by an
 *   enumerator. They are kept as reported and checked, never "fixed" — a
 *   corrected number would be one nobody reported.
 * - Leaders' phone numbers are never carried over. Only the chairperson's is
 *   offered, separately, as the group's contact when it has none.
 */

export const NEEDS_ASSESSMENT_SOURCE = "KOBO_IWL_VSLA_01";

// ---------------------------------------------------------------------------
// CSV
// ---------------------------------------------------------------------------

/** Parses a Kobo CSV export (`;`-separated, quoted, possibly with a BOM). */
export function parseKoboCsv(text: string, delimiter = ";"): Record<string, string>[] {
  const source = text.replace(/^\uFEFF/, "");
  const records: string[][] = [];
  let field = "";
  let record: string[] = [];
  let quoted = false;
  for (let i = 0; i < source.length; i += 1) {
    const char = source[i]!;
    if (quoted) {
      if (char === '"') {
        if (source[i + 1] === '"') {
          field += '"';
          i += 1;
        } else {
          quoted = false;
        }
      } else {
        field += char;
      }
      continue;
    }
    if (char === '"') {
      quoted = true;
    } else if (char === delimiter) {
      record.push(field);
      field = "";
    } else if (char === "\n" || char === "\r") {
      if (char === "\r" && source[i + 1] === "\n") i += 1;
      record.push(field);
      field = "";
      if (record.some((value) => value !== "")) records.push(record);
      record = [];
    } else {
      field += char;
    }
  }
  record.push(field);
  if (record.some((value) => value !== "")) records.push(record);

  const [header, ...rows] = records;
  if (!header) return [];
  const keys = header.map(cleanLabel);
  return rows.map((row) => Object.fromEntries(keys.map((key, index) => [key, (row[index] ?? "").trim()])));
}

/** Kobo writes the form's en dashes as U+FFFD in some exports. */
export function cleanLabel(label: string) {
  return label.replace(/\uFFFD/g, "–").replace(/\s+/g, " ").trim();
}

// ---------------------------------------------------------------------------
// Values
// ---------------------------------------------------------------------------

const BLANK = new Set(["", "n/a", "na", "none", "nil", "-"]);

function present(value: string | undefined) {
  const v = (value ?? "").trim();
  return BLANK.has(v.toLowerCase()) ? null : v;
}

export function yesNo(value: string | undefined): boolean | null {
  const v = present(value)?.toLowerCase();
  if (v === "yes" || v === "y" || v === "true" || v === "1") return true;
  if (v === "no" || v === "n" || v === "false" || v === "0") return false;
  return null;
}

export function integer(value: string | undefined): number | null {
  const v = present(value);
  if (v === null) return null;
  const n = Number(v.replace(/[,\s]/g, "").replace(/^kes/i, ""));
  return Number.isFinite(n) ? Math.round(n) : null;
}

/** Shillings as typed → cents. */
export function cents(value: string | undefined): number | null {
  const v = present(value);
  if (v === null) return null;
  const n = Number(v.replace(/[,\s]/g, "").replace(/^(kes|ksh)/i, ""));
  return Number.isFinite(n) ? Math.round(n * 100) : null;
}

/** A date-only value at UTC midday, so no timezone moves it to another day. */
export function dateOnly(value: string | undefined): Date | null {
  const v = present(value);
  const match = v?.match(/^(\d{4})-(\d{2})-(\d{2})/);
  if (!match) return null;
  return new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]), 12, 0, 0));
}

function titleCase(value: string | null) {
  if (!value) return null;
  return value
    .toLowerCase()
    .split(/\s+/)
    .map((word) => (word ? word[0]!.toUpperCase() + word.slice(1) : word))
    .join(" ");
}

// ---------------------------------------------------------------------------
// Sections (the form's own order) — what goes into answersJson
// ---------------------------------------------------------------------------

/** First question of each section, in the form's order. */
const SECTION_STARTS: Array<[string, string]> = [
  ["profile", "Group Name"],
  ["leadership", "Leadership Officers"],
  ["governance", "Governance Checklist"],
  ["finances", "Share Value per Member (KES)"],
  ["markets", "Main Commodities Produced"],
  ["records", "Record-Keeping Inspection"],
  ["training", "Training Priority Rating"],
  ["linkages", "Linked to a SACCO"],
  ["digital", "Members with Smartphones"],
  ["observations", "Key Strengths Observed"],
  ["signOff", "Enumerator Full Name"],
  ["meta", "_id"]
];

/** Never stored: personal phone numbers, and Kobo's own bookkeeping. */
function excluded(label: string) {
  return (
    /phone number/i.test(label) ||
    label.startsWith("_") ||
    label.startsWith("meta/") ||
    label === "__version__" ||
    label === "GPS Location"
  );
}

export function answersBySection(row: Record<string, string>) {
  const labels = Object.keys(row);
  const out: Record<string, Record<string, string>> = {};
  let section = "profile";
  for (const label of labels) {
    const start = SECTION_STARTS.find(([, first]) => first === label);
    if (start) section = start[0];
    if (section === "meta" || excluded(label)) continue;
    const value = present(row[label]);
    if (value === null) continue;
    (out[section] ??= {})[label] = value.replace(/\uFFFD/g, "–");
  }
  return out;
}

// ---------------------------------------------------------------------------
// Where the group is
// ---------------------------------------------------------------------------

/** The programme's area: Embu and Kirinyaga, generously bounded. */
const AREA = { minLat: -0.95, maxLat: -0.15, minLon: 37.0, maxLon: 37.95 };
/** Coordinates a phone reports before it has a fix (central Nairobi). */
const KNOWN_DEFAULTS: Array<[number, number]> = [[-1.2841, 36.8155]];
/** Readings less accurate than this do not place a group. */
export const MAX_GPS_PRECISION_M = 100;

export type GpsReading = { latitude: number; longitude: number; precisionM: number | null };

export function gpsOf(row: Record<string, string>): GpsReading | null {
  const latitude = Number(row["_GPS Location_latitude"]);
  const longitude = Number(row["_GPS Location_longitude"]);
  if (!Number.isFinite(latitude) || !Number.isFinite(longitude) || (latitude === 0 && longitude === 0)) return null;
  const precision = Number(row["_GPS Location_precision"]);
  return { latitude, longitude, precisionM: Number.isFinite(precision) ? precision : null };
}

export function gpsProblem(gps: GpsReading | null): string | null {
  if (!gps) return "no GPS reading";
  if (KNOWN_DEFAULTS.some(([lat, lon]) => Math.abs(gps.latitude - lat) < 0.001 && Math.abs(gps.longitude - lon) < 0.001)) {
    return "GPS is the phone's default location (central Nairobi), not the group's";
  }
  if (gps.latitude < AREA.minLat || gps.latitude > AREA.maxLat || gps.longitude < AREA.minLon || gps.longitude > AREA.maxLon) {
    return `GPS (${gps.latitude}, ${gps.longitude}) is outside Embu and Kirinyaga`;
  }
  return null;
}

/**
 * Which of the two counties a plausible reading falls in. Coarse — west of
 * 37.40°E is Kirinyaga, east is Embu, which separates Kerugoya/Inoi from
 * Kithimu/Manyatta/Runyenjes cleanly — and only ever used to CONFIRM what the
 * form says before a county is corrected, never to decide one alone.
 */
export function countyFromGps(gps: GpsReading): "Embu" | "Kirinyaga" {
  return gps.longitude < 37.4 ? "Kirinyaga" : "Embu";
}

// ---------------------------------------------------------------------------
// The submission, typed and checked
// ---------------------------------------------------------------------------

export interface ParsedNeedsAssessment {
  sourceReference: string;
  formVersion: string | null;
  groupName: string;
  county: string | null;
  subCounty: string | null;
  ward: string | null;
  assessedOn: Date | null;
  fieldOfficer: string | null;
  enumerator: string | null;
  chairperson: { name: string | null; phone: string | null };
  gps: GpsReading | null;
  gpsProblem: string | null;
  fields: {
    totalMembers: number | null;
    womenMembers: number | null;
    youthMembers: number | null;
    membersWithDisability: number | null;
    dateFormed: Date | null;
    completedCycles: number | null;
    currentCycle: number | null;
    shareValueCents: number | null;
    totalSavingsCents: number | null;
    loanPortfolioCents: number | null;
    arrearsCents: number | null;
    par30Cents: number | null;
    welfareBalanceCents: number | null;
    interestCents: number | null;
    smartphoneMembers: number | null;
    basicPhoneMembers: number | null;
    literacyPct: number | null;
    digitalChampion: boolean | null;
  };
  answers: Record<string, Record<string, string>>;
  qualityFlags: string[];
}

export function parseSubmission(row: Record<string, string>): ParsedNeedsAssessment {
  const get = (label: string) => row[label];
  const gps = gpsOf(row);
  const assessedOn = dateOnly(get("Visit Date"));
  const fields = {
    totalMembers: integer(get("Total Members")),
    womenMembers: integer(get("Women Members")),
    youthMembers: integer(get("Youth Members")),
    membersWithDisability: integer(get("Members with Disability")),
    dateFormed: dateOnly(get("Date Formed")),
    completedCycles: integer(get("Completed Cycles")),
    currentCycle: integer(get("Current Cycle Number")),
    shareValueCents: cents(get("Share Value per Member (KES)")),
    totalSavingsCents: cents(get("Total Savings (KES)")),
    loanPortfolioCents: cents(get("Active Loan Portfolio (KES)")),
    arrearsCents: cents(get("Loans in Arrears (KES)")),
    par30Cents: cents(get("Portfolio at Risk >30 Days (KES)")),
    welfareBalanceCents: cents(get("Welfare Fund Balance (KES)")),
    interestCents: cents(get("Interest Earned This Cycle (KES)")),
    smartphoneMembers: integer(get("Members with Smartphones")),
    basicPhoneMembers: integer(get("Members with Basic Phones Only")),
    literacyPct: integer(get("Estimated Member Literacy Rate (%)")),
    digitalChampion: yesNo(get("Digital Champion Available"))
  };

  const problem = gpsProblem(gps);
  const flags: string[] = [];
  if (problem) flags.push(problem);
  else if (gps?.precisionM && gps.precisionM > MAX_GPS_PRECISION_M) {
    flags.push(`GPS accuracy is ${Math.round(gps.precisionM)} m — too coarse to place the group`);
  }

  const total = fields.totalMembers;
  if (total !== null) {
    for (const [label, value] of [
      ["women", fields.womenMembers],
      ["youth", fields.youthMembers],
      ["members with a disability", fields.membersWithDisability],
      ["members with smartphones", fields.smartphoneMembers]
    ] as const) {
      if (value !== null && value > total) flags.push(`${label} (${value}) exceed total members (${total})`);
    }
  }
  const minShares = integer(get("Minimum Shares per Meeting"));
  if (minShares !== null && minShares > 1000) {
    flags.push(`minimum shares per meeting is ${minShares} — looks like a money figure typed into the wrong box`);
  }
  if (fields.shareValueCents !== null && fields.shareValueCents < 10_00) {
    flags.push(`share value of KSh ${fields.shareValueCents / 100} is implausibly low`);
  }
  const savings = fields.totalSavingsCents;
  const loans = fields.loanPortfolioCents;
  if (savings !== null && loans !== null && savings > 0 && loans > savings * 10) {
    flags.push("loan portfolio is more than ten times total savings");
  }
  if (loans !== null && fields.arrearsCents !== null && fields.arrearsCents > loans && loans >= 0) {
    flags.push("loans in arrears exceed the loan portfolio");
  }
  if (loans !== null && fields.par30Cents !== null && fields.par30Cents > loans) {
    flags.push("portfolio at risk exceeds the loan portfolio");
  }
  const lastShareOut = dateOnly(get("Last Shareout Date"));
  if (lastShareOut && assessedOn && lastShareOut > assessedOn) {
    flags.push("last share-out date is after the visit");
  }
  if (fields.dateFormed && assessedOn && fields.dateFormed > assessedOn) {
    flags.push("date formed is after the visit");
  }
  if (fields.literacyPct !== null && (fields.literacyPct < 0 || fields.literacyPct > 100)) {
    flags.push(`literacy rate ${fields.literacyPct}% is not a percentage`);
  }

  return {
    sourceReference: (get("_uuid") ?? "").trim(),
    formVersion: present(get("__version__")),
    groupName: (get("Group Name") ?? "").trim(),
    county: titleCase(present(get("County"))),
    subCounty: titleCase(present(get("Sub-County"))),
    ward: titleCase(present(get("Ward / Village"))),
    assessedOn,
    fieldOfficer: present(get("Field Officer")),
    enumerator: present(get("Enumerator Full Name")) ?? present(get("Completed By")),
    chairperson: {
      name: present(get("Chairperson – Full Name")),
      phone: present(get("Chairperson – Phone Number"))
    },
    gps,
    gpsProblem: problem,
    fields,
    answers: answersBySection(row),
    qualityFlags: flags
  };
}

// ---------------------------------------------------------------------------
// The partial scorecard (decided 2 Oct 2026: profile + partial scorecard)
// ---------------------------------------------------------------------------

export type ScorecardChoice = "YES" | "PARTIAL" | "NO";

/**
 * Form answers that mean the same as a scorecard v1 question. Only close
 * equivalents: an answer that is merely related is left out, because a
 * baseline built on loose matches would make every later scorecard look like
 * change. Each line says why it counts.
 */
export const SCORECARD_MAPPING: Array<{
  questionKey: string;
  from: string;
  why: string;
  map: (row: Record<string, string>) => ScorecardChoice | null;
}> = [
  {
    questionKey: "constitution_written",
    from: "Written Constitution / By-Laws Exists",
    why: "same question",
    map: (row) => choice(yesNo(row["Written Constitution / By-Laws Exists"]))
  },
  {
    questionKey: "committee_elected",
    from: "Leadership Formally Elected",
    why: "same question",
    map: (row) => choice(yesNo(row["Leadership Formally Elected"]))
  },
  {
    questionKey: "quorum_respected",
    from: "Quorum Requirement Defined",
    // A quorum written down is half of "required AND observed"; observing it
    // is what a field scorecard checks, so a defined quorum earns PARTIAL.
    why: "defined is part of required-and-observed",
    map: (row) => {
      const v = yesNo(row["Quorum Requirement Defined"]);
      return v === null ? null : v ? "PARTIAL" : "NO";
    }
  },
  {
    questionKey: "minutes_kept",
    from: "Meeting Minutes Recorded",
    why: "same question",
    map: (row) => choice(yesNo(row["Meeting Minutes Recorded"]))
  },
  {
    questionKey: "loan_policy_written",
    from: "Loan Approval Criteria",
    why: "criteria for approving loans are the loan policy",
    map: (row) => choice(yesNo(row["Loan Approval Criteria"]))
  },
  {
    questionKey: "bank_or_mobile_account",
    from: "Bank Account",
    why: "a bank account answers it; no bank account may still mean mobile money, so NO is not inferred",
    map: (row) => (yesNo(row["Bank Account"]) ? "YES" : null)
  },
  {
    questionKey: "three_key_control",
    from: "Three-Key System in Use",
    why: "same question",
    map: (row) => choice(yesNo(row["Three-Key System in Use"]))
  },
  {
    questionKey: "savings_recorded_passbook",
    from: "Member Passbook Up to Date",
    why: "an up-to-date passbook is every purchase entered in it",
    map: (row) => choice(yesNo(row["Member Passbook Up to Date"]))
  },
  {
    questionKey: "savings_recorded_ledger",
    from: "Savings Register Up to Date",
    why: "the savings register is the group's share ledger",
    map: (row) => choice(yesNo(row["Savings Register Up to Date"]))
  },
  {
    questionKey: "attendance_recorded",
    from: "Attendance Register Available",
    why: "a kept attendance register is attendance recorded",
    map: (row) => choice(yesNo(row["Attendance Register Available"]))
  },
  {
    questionKey: "social_fund_exists",
    from: "Welfare Fund Register Available / Welfare Fund Balance (KES)",
    why: "a welfare register or balance shows the fund exists; their absence does not prove it does not",
    map: (row) =>
      yesNo(row["Welfare Fund Register Available"]) || (cents(row["Welfare Fund Balance (KES)"]) ?? 0) > 0 ? "YES" : null
  },
  {
    questionKey: "digital_records",
    from: "Current Use of Digital Tools",
    why: "names the tools in use; IntelliCash or not answers it",
    map: (row) => {
      const v = present(row["Current Use of Digital Tools"]);
      if (v === null) return "NO";
      return /intelli/i.test(v) ? "YES" : "NO";
    }
  }
];

function choice(value: boolean | null): ScorecardChoice | null {
  return value === null ? null : value ? "YES" : "NO";
}

/** Note on scorecard questions the needs-assessment form never asked. */
export const NOT_ASKED_NOTE = "Not covered by the needs assessment form.";

/**
 * The baseline scorecard answers for one submission.
 *
 * The scoring rule counts an UNANSWERED question as 0 and keeps it in the
 * denominator, so a baseline answering a dozen of 44 questions would score
 * about a quarter whatever the group is like. The form simply did not ask the
 * rest, so — given [allQuestionKeys] — every other question is recorded as
 * NOT_APPLICABLE with that reason, and the percentage is over what was asked.
 * The coverage is shown beside it wherever this baseline is read.
 */
export function scorecardAnswers(row: Record<string, string>, allQuestionKeys: readonly string[] = []) {
  const asked = SCORECARD_MAPPING.flatMap((item) => {
    const mapped = item.map(row);
    return mapped ? [{ questionKey: item.questionKey, choice: mapped as string, note: `From the needs assessment: ${item.from}` }] : [];
  });
  const answered = new Set(asked.map((answer) => answer.questionKey));
  const notAsked = allQuestionKeys
    .filter((key) => !answered.has(key))
    .map((questionKey) => ({ questionKey, choice: "NOT_APPLICABLE", note: NOT_ASKED_NOTE }));
  return [...asked, ...notAsked];
}
