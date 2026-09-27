/**
 * Money on the payments screens. Unlike `formatKes`, keeps the cents: a
 * KSh 7.58 provider charge must not read as KSh 8.
 */
export function kesExact(cents: number | null | undefined) {
  if (cents === null || cents === undefined) return "—";
  return new Intl.NumberFormat("en-KE", {
    style: "currency",
    currency: "KES",
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(cents / 100);
}

/** "12.50" → 1250; blank or invalid → null. */
export function centsFromShillings(value: string) {
  if (value.trim() === "") return null;
  const number = Number(value);
  if (!Number.isFinite(number) || number < 0) return null;
  return Math.round(number * 100);
}

export const PROVIDER_LABELS: Record<string, string> = {
  MPESA_DARAJA: "M-Pesa",
  PAYSTACK: "Paystack"
};

export const STATE_LABELS: Record<string, string> = {
  INITIATED: "Started",
  PROCESSING: "Waiting for payer",
  SUCCESSFUL: "Paid, confirming",
  VERIFIED: "Verified, not posted",
  LEDGER_POSTED: "In the books",
  HELD: "Held for checking",
  FAILED: "Failed",
  CANCELLED: "Cancelled",
  EXPIRED: "Expired",
  REVERSED: "Reversed",
  REFUNDED: "Refunded",
  COMPLETED_LEGACY: "Completed (before fees)"
};

export const SETTLEMENT_LABELS: Record<string, string> = {
  NOT_REQUIRED: "Not needed",
  PENDING: "Waiting",
  IN_SETTLEMENT: "In a payout",
  SETTLED: "Paid to group",
  SETTLEMENT_FAILED: "Payout failed",
  UNKNOWN: "Payout unknown",
  AWAITING_APPROVAL: "Needs approval",
  QUEUED: "Queued",
  PROCESSING: "Sending",
  FAILED: "Failed",
  CANCELLED: "Cancelled"
};

export const DESTINATION_LABELS: Record<string, string> = {
  MPESA_PHONE: "M-Pesa phone",
  MPESA_PAYBILL: "M-Pesa paybill",
  MPESA_TILL: "M-Pesa till (Buy Goods)",
  PAYSTACK_BANK: "Bank account (Paystack)",
  PAYSTACK_MOBILE_MONEY: "Mobile money (Paystack)"
};
