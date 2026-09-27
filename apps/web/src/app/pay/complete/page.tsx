import React from "react";
import type { Metadata } from "next";
import { PublicSiteFooter } from "../../../components/public-site-footer";
import { PublicSiteHeader } from "../../../components/public-site-header";

const playStoreUrl = "https://play.google.com/store/apps/details?id=com.intellicash.app";

export const metadata: Metadata = {
  title: "Payment | Intelli Cash",
  description: "Return to the Intelli-Cash app to see your payment."
};

/**
 * Where Paystack sends a payer after its checkout page.
 *
 * Deliberately says nothing about success: Paystack returns here whether or
 * not the card or mobile money went through, and only the server's own check
 * with Paystack decides. The app shows the outcome once it is confirmed.
 */
export default async function PaymentCompletePage({
  searchParams
}: {
  searchParams: Promise<{ reference?: string; trxref?: string }>;
}) {
  const params = await searchParams;
  const reference = params.reference ?? params.trxref ?? null;

  return (
    <main className="landing-page">
      <section className="store-page-hero">
        <PublicSiteHeader ariaLabel="Payment navigation" playStoreUrl={playStoreUrl} />
        <div className="store-page-hero-copy">
          <p className="eyebrow">Payment</p>
          <h1>Thank you — go back to the app</h1>
          <p>
            Your payment is being confirmed with Paystack. The Intelli-Cash app shows it as soon as it is confirmed, and it
            is added to your group&apos;s books automatically.
          </p>
          <p>
            <strong>Do not pay again</strong> if the app still says it is waiting — confirmation can take a minute.
          </p>
          {reference ? (
            <p>
              Your reference: <code>{reference}</code>
            </p>
          ) : null}
        </div>
      </section>
      <PublicSiteFooter playStoreUrl={playStoreUrl} />
    </main>
  );
}
