import React from "react";
import { HandCoins, HeartHandshake, Landmark, WalletCards } from "@/lib/theme-icons";
import { StatCard } from "../../components/dashboard/stat-card";
import { formatKes } from "../../lib/api";

/**
 * The parts of a group's statement (GET /reports/group/:id) its money cards
 * show. The same figures as the financial reports, the portfolio and the
 * phone, because they all come from the one statement.
 */
export interface GroupMoneyStatement {
  suppressed: boolean;
  cycle: { number: number; status: string } | null;
  members: { active: number; total: number };
  loanFund: { sharesCents: number; closingCents: number } | null;
  socialFund: { closingCents: number; welfarePaidCents: number } | null;
  loans: { activeCount: number; pastDueCount: number; outstandingCents: number; par30Rate: number | null } | null;
}

/**
 * Savings this cycle, loan fund cash, loans outstanding and the social fund.
 * With no statement yet (still loading) the cards hold their place.
 */
export function GroupMoneyCards({ statement }: { statement: GroupMoneyStatement | null }) {
  const waiting = "…";
  const par30 = statement?.loans?.par30Rate;
  const welfarePaid = statement?.socialFund?.welfarePaidCents ?? 0;

  return (
    <>
      <StatCard
        icon={<WalletCards size={20} />}
        label="Shares this cycle"
        note={statement ? `${statement.members.active} active members · cycle ${statement.cycle?.number ?? "-"}` : undefined}
        value={statement ? formatKes(statement.loanFund?.sharesCents ?? 0) : waiting}
      />
      <StatCard
        icon={<Landmark size={20} />}
        label="Loan fund cash"
        note="In the box, available to lend"
        value={statement ? formatKes(statement.loanFund?.closingCents ?? 0) : waiting}
      />
      <StatCard
        icon={<HandCoins size={20} />}
        label="Loans outstanding"
        note={
          statement?.loans
            ? `${statement.loans.activeCount} active · PAR30 ${par30 === null || par30 === undefined ? "-" : `${par30}%`}`
            : undefined
        }
        value={statement ? formatKes(statement.loans?.outstandingCents ?? 0) : waiting}
      />
      <StatCard
        icon={<HeartHandshake size={20} />}
        label="Social fund"
        note={welfarePaid > 0 ? `After ${formatKes(welfarePaid)} welfare paid` : "Contributions and fines, less welfare"}
        value={statement ? formatKes(statement.socialFund?.closingCents ?? 0) : waiting}
      />
    </>
  );
}
