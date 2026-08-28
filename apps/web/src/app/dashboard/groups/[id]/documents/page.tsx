"use client";

import { use, useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, ClipboardList } from "@/lib/theme-icons";
import { apiFetch, formatDate, humanizeEnum } from "../../../../../lib/api";

/**
 * A group's document register.
 *
 * The chip on each row is DERIVED, not stored. `presence`, `verification` and
 * `expiresOn` are three separate facts underneath it, which is what lets the
 * register answer "how many verified certificates expire this quarter" — a
 * question that becomes unanswerable the moment expiry overwrites verification.
 *
 * Recording that a document is held and deciding it is genuine are separate
 * actions here for the same reason they are separate on the server: the person
 * who collected the evidence should not be the one who signs it off.
 */
interface DocumentRow {
  documentType: string;
  state: string;
  label: string;
  presence: string;
  verification: string;
  expiresOn: string | null;
  daysUntilExpiry: number | null;
  needsAttention: boolean;
  notes: string | null;
  verifiedAt: string | null;
}

interface RegisterResponse {
  group: { id: string; name: string; code: string };
  documents: DocumentRow[];
  summary: {
    total: number;
    verified: number;
    missing: number;
    expired: number;
    needsAttention: number;
    percentVerified: number;
  };
}

export default function GroupDocumentsPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [data, setData] = useState<RegisterResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  async function load() {
    setData(await apiFetch<RegisterResponse>(`/groups/${id}/documents`));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the register."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  async function setPresence(documentType: string, presence: string) {
    setBusy(documentType);
    setMessage(null);
    try {
      await apiFetch(`/groups/${id}/documents/${documentType}`, {
        method: "PUT",
        body: JSON.stringify({ presence })
      });
      await load();
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save." });
    } finally {
      setBusy(null);
    }
  }

  async function verify(documentType: string, verification: string) {
    setBusy(documentType);
    setMessage(null);
    try {
      await apiFetch(`/groups/${id}/documents/${documentType}/verify`, {
        method: "POST",
        body: JSON.stringify({ verification })
      });
      await load();
    } catch (e) {
      setMessage({
        ok: false,
        text: e instanceof Error ? e.message : "Could not record that decision."
      });
    } finally {
      setBusy(null);
    }
  }

  if (loading) return <div className="loading-panel">Loading register…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">No register.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href={`/dashboard/groups/${id}`}>
            <ArrowLeft size={17} />
            <span>{data.group.name}</span>
          </Link>
          <h2>Documents</h2>
          <p>
            {data.summary.verified} of {data.summary.total} verified.
            {data.summary.needsAttention > 0
              ? ` ${data.summary.needsAttention} need attention.`
              : " Nothing outstanding."}
          </p>
        </div>
        <ClipboardList size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <article className="data-card">
        <table className="data-table">
          <thead>
            <tr>
              <th>Document</th>
              <th>Status</th>
              <th>Expires</th>
              <th>Held?</th>
              <th>Decision</th>
            </tr>
          </thead>
          <tbody>
            {data.documents.map((doc) => (
              <tr key={doc.documentType}>
                <td>{humanizeEnum(doc.documentType)}</td>
                <td>
                  <span className={pillClass(doc.state)}>{doc.label}</span>
                </td>
                <td>
                  {doc.expiresOn ? (
                    <>
                      {formatDate(doc.expiresOn)}
                      {doc.daysUntilExpiry !== null && doc.daysUntilExpiry < 0 ? (
                        <div className="eyebrow">{Math.abs(doc.daysUntilExpiry)} days ago</div>
                      ) : doc.daysUntilExpiry !== null ? (
                        <div className="eyebrow">in {doc.daysUntilExpiry} days</div>
                      ) : null}
                    </>
                  ) : (
                    "—"
                  )}
                </td>
                <td>
                  <button
                    className="button subtle"
                    disabled={busy === doc.documentType}
                    onClick={() =>
                      setPresence(
                        doc.documentType,
                        doc.presence === "PRESENT" ? "MISSING" : "PRESENT"
                      )
                    }
                  >
                    {doc.presence === "PRESENT" ? "Held" : "Not held"}
                  </button>
                </td>
                <td>
                  {doc.presence === "PRESENT" ? (
                    <div className="button-row">
                      <button
                        className="button subtle"
                        disabled={busy === doc.documentType || doc.verification === "VERIFIED"}
                        onClick={() => verify(doc.documentType, "VERIFIED")}
                      >
                        Verify
                      </button>
                      <button
                        className="button subtle"
                        disabled={busy === doc.documentType || doc.verification === "REJECTED"}
                        onClick={() => verify(doc.documentType, "REJECTED")}
                      >
                        Reject
                      </button>
                    </div>
                  ) : (
                    <span className="eyebrow">Record it as held first</span>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </article>

      <p className="eyebrow">
        &ldquo;Expired&rdquo; is worked out from the expiry date each time this page loads, so it
        can never be out of step with the calendar — and a document that has lapsed still records
        that it was once verified.
      </p>
    </section>
  );
}

/**
 * Maps a derived state onto the house pill variants.
 *
 * Red for what is wrong now, gold for what will be wrong soon, plain green
 * for settled. Deliberately keyed on the derived state rather than the stored
 * columns, so the colour and the label can never disagree.
 */
function pillClass(state: string) {
  if (state === "MISSING" || state === "EXPIRED" || state === "REJECTED") return "pill red";
  if (state === "UNVERIFIED") return "pill gold";
  return "pill";
}
