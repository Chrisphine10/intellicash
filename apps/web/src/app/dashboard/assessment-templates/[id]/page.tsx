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

  const questionCount = template.sections.reduce((sum, section) => sum + section.questions.length, 0);

  // Laid out with the theme's card pieces — a <header> per card, `.card-body`
  // for padding, `.card-note` for prose. This page used `.eyebrow` (a small
  // uppercase green LABEL) for every paragraph and put content straight into
  // `.data-card`, so explanations shouted in green capitals and sat flush
  // against the card border.
  return (
    <section className="dashboard-section scorecard-detail">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/assessment-templates">
            <ArrowLeft size={17} />
            <span>Scorecards</span>
          </Link>
          <h2>
            {template.title} — v{template.version}
          </h2>
          <p className="card-note">
            {template.status === "PUBLISHED"
              ? "Published and locked. Clone it to make changes; this version has to keep scoring the assessments already made against it."
              : "Draft. Nothing uses it until it is published."}
          </p>
        </div>
        <span className={template.status === "PUBLISHED" ? "pill blue" : "pill gold"}>
          <ClipboardList size={14} /> {template.status === "PUBLISHED" ? "Published" : "Draft"}
        </span>
      </header>

      {message ? (
        <p className={message.ok ? "notice success" : "notice warning"}>{message.text}</p>
      ) : null}

      <article className="data-card">
        <header>
          <div>
            <h3>Summary</h3>
            <span>Totals are computed from the questions — never typed in</span>
          </div>
        </header>
        <div className="card-body">
          <div className="fact-grid">
            <div className="fact">
              <span className="label">Total points</span>
              <span className="value metric-value">{computedMaxPoints}</span>
            </div>
            <div className="fact">
              <span className="label">Sections</span>
              <span className="value metric-value small">{template.sections.length}</span>
            </div>
            <div className="fact">
              <span className="label">Questions</span>
              <span className="value metric-value small">{questionCount}</span>
            </div>
            <div className="fact">
              <span className="label">Bands</span>
              <span className="value metric-value small">{bands.length}</span>
            </div>
          </div>
        </div>
      </article>

      {issues.length ? (
        <article className="data-card">
          <header>
            <div>
              <h3>Not publishable yet</h3>
              <span>Fix these before publishing</span>
            </div>
            <span className="pill red">{issues.length}</span>
          </header>
          <div className="card-body">
            <ul className="scorecard-issues">
              {issues.map((issue) => (
                <li key={`${issue.path}-${issue.message}`}>
                  <code>{issue.path}</code> — {issue.message}
                </li>
              ))}
            </ul>
          </div>
        </article>
      ) : null}

      <article className="data-card">
        <header>
          <div>
            <h3>Bands</h3>
            <span>Every score from 0 to {computedMaxPoints} needs exactly one band</span>
          </div>
          <span className={coverageOk ? "pill blue" : "pill red"}>{coverageOk ? "Complete" : "Gaps"}</span>
        </header>
        <div className="card-body">
          <p className="card-note">
            A gap means some achievable score has no band, and the first time anyone notices is
            when a real assessment lands in it.
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
              <p className={coverageOk ? "card-note" : "notice warning"}>{coverageSummary}</p>
            </>
          ) : null}

          <div className="table-wrap">
            <table className="data-table scorecard-bands">
              <thead>
                <tr>
                  <th>Band</th>
                  <th>From (points)</th>
                  <th>To (points)</th>
                </tr>
              </thead>
              <tbody>
                {bands.map((band, index) => (
                  <tr key={band.key}>
                    <td>
                      <strong>{band.label}</strong>
                    </td>
                    <td>
                      <input
                        aria-label={`${band.label} from`}
                        disabled={!editable || busy}
                        onChange={(event) => updateBand(index, { minPoints: Number(event.target.value) })}
                        type="number"
                        value={band.minPoints}
                      />
                    </td>
                    <td>
                      <input
                        aria-label={`${band.label} to`}
                        disabled={!editable || busy}
                        onChange={(event) => updateBand(index, { maxPoints: Number(event.target.value) })}
                        type="number"
                        value={band.maxPoints}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {editable ? (
            <div className="scorecard-actions">
              <button className="button secondary" disabled={busy} onClick={saveBands} type="button">
                {busy ? "Saving…" : "Save bands"}
              </button>
              <button
                className="button"
                disabled={busy || !template.validation.ok}
                onClick={publish}
                type="button"
              >
                Publish this version
              </button>
            </div>
          ) : null}
        </div>
      </article>

      {template.sections.map((section) => (
        <article className="data-card" key={section.key}>
          <header>
            <div>
              <h3>{section.title}</h3>
              <span>{section.questions.length} questions</span>
            </div>
            <span className="pill">
              {section.questions.reduce((sum, question) => sum + question.weight, 0)} points
            </span>
          </header>
          <div className="card-body">
            {section.description ? <p className="card-note">{section.description}</p> : null}
            <div className="table-wrap">
              <table className="data-table scorecard-questions">
                <thead>
                  <tr>
                    <th>Question</th>
                    <th>Key</th>
                    <th className="numeric">Weight</th>
                  </tr>
                </thead>
                <tbody>
                  {section.questions.map((question) => (
                    <tr key={question.key}>
                      <td>
                        <span className="scorecard-prompt">{question.prompt}</span>
                        {question.guidance ? <span className="card-note">{question.guidance}</span> : null}
                      </td>
                      <td>
                        <code>{question.key}</code>
                      </td>
                      <td className="numeric">{question.weight}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </article>
      ))}

      <p className="card-note">
        Section and question keys are what cross-visit trends join on. Renaming a section&apos;s
        title is free; changing its key breaks the history.
      </p>
    </section>
  );
}
