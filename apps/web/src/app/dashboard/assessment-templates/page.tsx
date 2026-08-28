"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { ClipboardList } from "@/lib/theme-icons";
import { apiFetch, formatDate } from "../../../lib/api";

/**
 * The versions of the field scorecard.
 *
 * A published version is immutable and stays listed forever: every assessment
 * ever scored points at one, and that is what makes a two-year-old visit
 * defensible. Changing the form means cloning the current version to a new
 * draft, editing that, and publishing it.
 */
interface TemplateRow {
  id: string;
  familyKey: string;
  version: number;
  status: string;
  title: string;
  description: string | null;
  maxPoints: number | null;
  publishedAt: string | null;
  checksum: string | null;
  sectionCount: number;
  assessmentCount: number;
}

export default function AssessmentTemplatesPage() {
  const [templates, setTemplates] = useState<TemplateRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  async function load() {
    setTemplates(await apiFetch<TemplateRow[]>("/assessment-templates"));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load templates."))
      .finally(() => setLoading(false));
  }, []);

  async function clone(id: string) {
    setBusy(true);
    setMessage(null);
    try {
      const created = await apiFetch<{ version: number }>(
        `/assessment-templates/${id}/clone`,
        { method: "POST" }
      );
      await load();
      setMessage(`Draft v${created.version} created. Edit it, then publish.`);
    } catch (e) {
      setMessage(e instanceof Error ? e.message : "Could not clone the template.");
    } finally {
      setBusy(false);
    }
  }

  if (loading) return <div className="loading-panel">Loading scorecards…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;

  const published = templates.find((template) => template.status === "PUBLISHED");

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <h2>Assessment scorecard</h2>
          <p>
            The form field agents complete during a visit. Each question scores Yes,
            Partial or No; the total is computed from the questions themselves.
          </p>
        </div>
        <ClipboardList size={22} />
      </header>

      {message ? <div className="dashboard-notice">{message}</div> : null}

      {!published ? (
        <div className="dashboard-notice error">
          No version is published, so agents cannot record an assessment. Publish a
          draft below.
        </div>
      ) : null}

      <article className="data-card">
        <table className="data-table">
          <thead>
            <tr>
              <th>Version</th>
              <th>Status</th>
              <th>Sections</th>
              <th>Points</th>
              <th>Assessments</th>
              <th>Published</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {templates.map((template) => (
              <tr key={template.id}>
                <td>
                  <Link href={`/dashboard/assessment-templates/${template.id}`}>
                    v{template.version}
                  </Link>
                  <div className="eyebrow">{template.title}</div>
                </td>
                <td>{template.status}</td>
                <td>{template.sectionCount}</td>
                <td>{template.maxPoints ?? "—"}</td>
                <td>{template.assessmentCount}</td>
                <td>
                  {template.publishedAt
                    ? formatDate(template.publishedAt)
                    : "—"}
                </td>
                <td>
                  {template.status === "DRAFT" ? (
                    <Link
                      className="button subtle"
                      href={`/dashboard/assessment-templates/${template.id}`}
                    >
                      Edit
                    </Link>
                  ) : (
                    <button
                      className="button subtle"
                      disabled={busy}
                      onClick={() => clone(template.id)}
                    >
                      Clone to draft
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </article>

      <p className="eyebrow">
        A published version can never be edited — assessments already scored against
        it have to keep meaning what they meant. Clone it instead; the old version
        stays exactly as it was.
      </p>
    </section>
  );
}
