"use client";

import { use, useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { ArrowLeft, ClipboardList } from "@/lib/theme-icons";
import { apiFetch } from "../../../../lib/api";
import { bandCoverage, coverageIsComplete } from "../../../../lib/band-coverage";

/**
 * One version of the scorecard: its questions, its weights, and its bands.
 *
 * A DRAFT is editable here. A PUBLISHED version is read-only — not as a UI
 * courtesy but because the server refuses, and it refuses because every
 * assessment scored under it stores a frozen snapshot that has to keep
 * matching.
 *
 * The total points are shown live and are never typed in: they are the sum of
 * the question weights. Bands are checked against that total, so an author can
 * see a gap before publishing rather than after a real visit lands in it.
 */
interface Question {
  key: string;
  prompt: string;
  guidance?: string;
  weight: number;
  position: number;
  requiresNote?: boolean;
}

interface Section {
  key: string;
  title: string;
  description?: string;
  position: number;
  questions: Question[];
}

interface Band {
  key: string;
  label: string;
  minPoints: number;
  maxPoints: number;
  guidance?: string;
}

interface ValidationIssue {
  path: string;
  message: string;
}

interface TemplateDetail {
  id: string;
  familyKey: string;
  version: number;
  status: string;
  title: string;
  description?: string;
  maxPoints: number | null;
  publishedAt: string | null;
  sections: Section[];
  bands: Band[];
  validation: { ok: true; maxPoints: number } | { ok: false; issues: ValidationIssue[] };
}

export default function AssessmentTemplateDetailPage({
  params
}: {
  params: Promise<{ id: string }>;
}) {
  const { id } = use(params);
  const [template, setTemplate] = useState<TemplateDetail | null>(null);
  const [bands, setBands] = useState<Band[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  async function load() {
    const detail = await apiFetch<TemplateDetail>(`/assessment-templates/${id}`);
    setTemplate(detail);
    setBands(detail.bands);
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the template."))
      .finally(() => setLoading(false));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [id]);

  /**
   * The live total, recomputed from the questions on screen. Never read from a
   * stored field — that is the whole point: no constant to fall out of date.
   */
  const computedMaxPoints = useMemo(() => {
    if (!template) return 0;
    return template.sections
      .flatMap((section) => section.questions)
      .reduce((sum, question) => sum + (Number.isFinite(question.weight) ? question.weight : 0), 0);
  }, [template]);

  const editable = template?.status === "DRAFT";

  async function saveBands() {
    if (!template) return;
    setBusy(true);
    setMessage(null);
    try {
      await apiFetch(`/assessment-templates/${template.id}`, {
        method: "PUT",
        body: JSON.stringify({
          title: template.title,
          description: template.description,
          sections: template.sections,
          bands
        })
      });
      await load();
      setMessage({ ok: true, text: "Saved." });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "Could not save." });
    } finally {
      setBusy(false);
    }
  }

  async function publish() {
    if (!template) return;
    setBusy(true);
    setMessage(null);
    try {
      const result = await apiFetch<{ version: number; maxPoints: number }>(
        `/assessment-templates/${template.id}/publish`,
        { method: "POST" }
      );
      await load();
      setMessage({
        ok: true,
        text: `Published v${result.version} at ${result.maxPoints} points. It is now locked.`
      });
    } catch (e) {
      setMessage({
        ok: false,
        text: e instanceof Error ? e.message : "Could not publish this version."
      });
    } finally {
      setBusy(false);
    }
  }

  function updateBand(index: number, patch: Partial<Band>) {
    setBands((current) =>
      current.map((band, i) => (i === index ? { ...band, ...patch } : band))
    );
  }

  if (loading) return <div className="loading-panel">Loading scorecard…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!template) return <div className="empty-state">Template not found.</div>;

  const issues = template.validation.ok ? [] : template.validation.issues;

  const coverage = bandCoverage(bands, computedMaxPoints);
  const coverageOk = coverageIsComplete(coverage);
  const coverageSummary = coverageOk
    ? `Every score from 0 to ${computedMaxPoints} has exactly one band.`
    : coverage
        .filter((segment) => segment.kind !== "band")
        .map((segment) => segment.label)
        .join(" · ") || "Nothing to cover yet.";

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/assessment-templates">
            <ArrowLeft size={17} />
            <span>Scorecards</span>
          </Link>
          <h2>
            {template.title} — v{template.version}
          </h2>
          <p>
            {template.status === "PUBLISHED"
              ? "Published and locked. Clone it to make changes; this version has to keep scoring the assessments already made against it."
              : "Draft. Nothing uses it until it is published."}
          </p>
        </div>
        <ClipboardList size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <article className="data-card">
        <h3>Total points</h3>
        <p className="metric-value">{computedMaxPoints}</p>
        <p className="eyebrow">
          The sum of every question weight, computed here and again on the server at
          publish. It is never typed in, so adding a question moves it and nothing
          has to be kept in step by hand.
        </p>
      </article>

      {issues.length ? (
        <article className="data-card">
          <h3>Not publishable yet</h3>
          <ul>
            {issues.map((issue) => (
              <li key={`${issue.path}-${issue.message}`}>
                <code>{issue.path}</code> — {issue.message}
              </li>
            ))}
          </ul>
        </article>
      ) : null}

      <article className="data-card">
        <h3>Bands</h3>
        <p className="eyebrow">
          Bands must cover every score from 0 to {computedMaxPoints} with no gap and
          no overlap. A gap means some achievable score has no band, and the first
          time anyone notices is when a real assessment lands in it.
        </p>

        {/*
          * The strip draws the whole 0..total range so a gap or an overlap is
          * something you SEE rather than something you infer from a validation
          * path. The commonest way to create one is to add a question: the
          * total moves and the top band quietly stops short of it.
          */}
        {coverage.length ? (
          <>
            <div className="band-strip" role="img" aria-label={coverageSummary}>
              {coverage.map((segment) => (
                <span
                  className={`band-strip-segment ${segment.kind}`}
                  key={`${segment.kind}-${segment.from}`}
                  style={{ width: `${segment.widthPercent}%` }}
                  title={segment.label}
                >
                  <span className="band-strip-label">{segment.label}</span>
                </span>
              ))}
            </div>
            <p className={coverageOk ? "eyebrow" : "dashboard-notice error"}>{coverageSummary}</p>
          </>
        ) : null}
        <table className="data-table">
          <thead>
            <tr>
              <th>Band</th>
              <th>From</th>
              <th>To</th>
            </tr>
          </thead>
          <tbody>
            {bands.map((band, index) => (
              <tr key={band.key}>
                <td>{band.label}</td>
                <td>
                  <input
                    type="number"
                    value={band.minPoints}
                    disabled={!editable || busy}
                    onChange={(event) =>
                      updateBand(index, { minPoints: Number(event.target.value) })
                    }
                  />
                </td>
                <td>
                  <input
                    type="number"
                    value={band.maxPoints}
                    disabled={!editable || busy}
                    onChange={(event) =>
                      updateBand(index, { maxPoints: Number(event.target.value) })
                    }
                  />
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        {editable ? (
          <div className="button-row">
            <button className="button" disabled={busy} onClick={saveBands}>
              {busy ? "Saving…" : "Save bands"}
            </button>
            <button
              className="button"
              disabled={busy || !template.validation.ok}
              onClick={publish}
            >
              Publish this version
            </button>
          </div>
        ) : null}
      </article>

      {template.sections.map((section) => (
        <article className="data-card" key={section.key}>
          <h3>
            {section.title}{" "}
            <span className="eyebrow">
              {section.questions.reduce((sum, question) => sum + question.weight, 0)} points
            </span>
          </h3>
          {section.description ? <p className="eyebrow">{section.description}</p> : null}
          <table className="data-table">
            <thead>
              <tr>
                <th>Question</th>
                <th>Key</th>
                <th>Weight</th>
              </tr>
            </thead>
            <tbody>
              {section.questions.map((question) => (
                <tr key={question.key}>
                  <td>
                    {question.prompt}
                    {question.guidance ? (
                      <div className="eyebrow">{question.guidance}</div>
                    ) : null}
                  </td>
                  <td>
                    <code>{question.key}</code>
                  </td>
                  <td>{question.weight}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </article>
      ))}

      <p className="eyebrow">
        Section and question keys are what cross-visit trends join on. Renaming a
        section's title is free; changing its key breaks the history.
      </p>
    </section>
  );
}
