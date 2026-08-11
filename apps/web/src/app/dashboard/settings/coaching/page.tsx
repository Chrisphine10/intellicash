"use client";

import type { FormEvent } from "react";
import { useEffect, useState } from "react";
import Link from "next/link";
import { ArrowLeft, GraduationCap } from "@/lib/theme-icons";
import { apiFetch } from "../../../../lib/api";

/**
 * The coaching topics an agent can record, and the questions a group is asked
 * to rate the coaching against.
 *
 * A topic's KEY is never editable. Keys are what recorded sessions and
 * cross-visit trends join on; changing one would orphan every session filed
 * under the old value. Titles are free to change because each session snapshots
 * the title it was recorded with.
 *
 * Removing a topic that has been used retires it instead of deleting it — it
 * leaves the phone's list, and the history still reads.
 */
interface Topic {
  key: string;
  title: string;
  description: string | null;
  position: number;
  isActive: boolean;
}

interface Dimension {
  key: string;
  title: string;
  position: number;
  isActive: boolean;
}

interface CatalogueResponse {
  topics: Topic[];
  dimensions: Dimension[];
}

export default function CoachingSettingsPage() {
  const [data, setData] = useState<CatalogueResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState<string | null>(null);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  const [topicKey, setTopicKey] = useState("");
  const [topicTitle, setTopicTitle] = useState("");
  const [topicDescription, setTopicDescription] = useState("");
  const [dimensionKey, setDimensionKey] = useState("");
  const [dimensionTitle, setDimensionTitle] = useState("");

  async function load() {
    setData(await apiFetch<CatalogueResponse>("/mentorship-topics/all"));
  }

  useEffect(() => {
    load()
      .catch((e) => setError(e instanceof Error ? e.message : "Unable to load the coaching list."))
      .finally(() => setLoading(false));
  }, []);

  /**
   * One write, then a reload.
   *
   * An action may return its own wording — the delete does, because "retired,
   * because 4 sessions still reference it" is the whole point of that call and
   * a generic "Removed." would hide it.
   */
  async function run(id: string, action: () => Promise<string | void>, success: string) {
    setBusy(id);
    setMessage(null);
    try {
      const specific = await action();
      await load();
      setMessage({ ok: true, text: specific || success });
    } catch (e) {
      setMessage({ ok: false, text: e instanceof Error ? e.message : "That did not work." });
    } finally {
      setBusy(null);
    }
  }

  async function addTopic(event: FormEvent) {
    event.preventDefault();
    await run(
      "new-topic",
      async () => {
        await apiFetch("/mentorship-topics", {
          method: "POST",
          body: JSON.stringify({
            key: topicKey.trim(),
            title: topicTitle.trim(),
            description: topicDescription.trim() || undefined,
            position: (data?.topics.length ?? 0) + 1
          })
        });
        setTopicKey("");
        setTopicTitle("");
        setTopicDescription("");
      },
      "Topic added. It appears on agents' phones the next time they sync."
    );
  }

  async function addDimension(event: FormEvent) {
    event.preventDefault();
    await run(
      "new-dimension",
      async () => {
        await apiFetch("/mentorship-dimensions", {
          method: "POST",
          body: JSON.stringify({
            key: dimensionKey.trim(),
            title: dimensionTitle.trim(),
            position: (data?.dimensions.length ?? 0) + 1
          })
        });
        setDimensionKey("");
        setDimensionTitle("");
      },
      "Rating question added."
    );
  }

  if (loading) return <div className="loading-panel">Loading coaching settings…</div>;
  if (error) return <div className="dashboard-notice error">{error}</div>;
  if (!data) return <div className="empty-state">Nothing to configure.</div>;

  return (
    <section className="dashboard-section">
      <header className="page-heading">
        <div>
          <Link className="inline-back" href="/dashboard/settings">
            <ArrowLeft size={17} />
            <span>Settings</span>
          </Link>
          <h2>Coaching topics</h2>
          <p>
            What an agent can record coaching a group on, and what the group is asked to rate it
            against afterwards.
          </p>
        </div>
        <GraduationCap size={22} />
      </header>

      {message ? (
        <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div>
      ) : null}

      <article className="data-card">
        <header>
          <div>
            <h3>Topics</h3>
            <span>
              {data.topics.filter((topic) => topic.isActive).length} in use ·{" "}
              {data.topics.filter((topic) => !topic.isActive).length} retired
            </span>
          </div>
        </header>
        <table className="data-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Key</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.topics.map((topic) => (
              <tr key={topic.key}>
                <td>
                  <TitleEditor
                    busy={busy === topic.key}
                    value={topic.title}
                    onSave={(title) =>
                      run(
                        topic.key,
                        () =>
                          apiFetch(`/mentorship-topics/${topic.key}`, {
                            method: "PATCH",
                            body: JSON.stringify({ title })
                          }).then(() => undefined),
                        "Renamed. Sessions already recorded keep the wording they were filed under."
                      )
                    }
                  />
                  {topic.description ? <div className="eyebrow">{topic.description}</div> : null}
                </td>
                <td>
                  <code>{topic.key}</code>
                  <div className="eyebrow">Fixed — history joins on it</div>
                </td>
                <td>
                  <span className={topic.isActive ? "pill" : "pill gold"}>
                    {topic.isActive ? "In use" : "Retired"}
                  </span>
                </td>
                <td>
                  <div className="button-row">
                    {topic.isActive ? (
                      <button
                        className="button subtle"
                        disabled={busy === topic.key}
                        onClick={() =>
                          run(
                            topic.key,
                            async () => {
                              const result = await apiFetch<{ retired: boolean; sessions: number }>(
                                `/mentorship-topics/${topic.key}`,
                                { method: "DELETE" }
                              );
                              return result.retired
                                ? `Retired rather than deleted — ${result.sessions} recorded session${result.sessions === 1 ? "" : "s"} still reference it, and they must stay readable.`
                                : "Deleted. Nothing had used it.";
                            },
                            "Removed."
                          )
                        }
                      >
                        Remove
                      </button>
                    ) : (
                      <button
                        className="button subtle"
                        disabled={busy === topic.key}
                        onClick={() =>
                          run(
                            topic.key,
                            () =>
                              apiFetch(`/mentorship-topics/${topic.key}`, {
                                method: "PATCH",
                                body: JSON.stringify({ isActive: true })
                              }).then(() => undefined),
                            "Back in use."
                          )
                        }
                      >
                        Put back
                      </button>
                    )}
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <form onSubmit={addTopic}>
          <h4>Add a topic</h4>
          <label>
            Key
            <input
              value={topicKey}
              disabled={busy === "new-topic"}
              onChange={(event) =>
                setTopicKey(event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ""))
              }
              placeholder="market_linkage"
              required
            />
          </label>
          <label>
            Title
            <input
              maxLength={200}
              value={topicTitle}
              disabled={busy === "new-topic"}
              onChange={(event) => setTopicTitle(event.target.value)}
              placeholder="Market linkage"
              required
            />
          </label>
          <label>
            Description
            <input
              maxLength={1000}
              value={topicDescription}
              disabled={busy === "new-topic"}
              onChange={(event) => setTopicDescription(event.target.value)}
              placeholder="Optional — shown under the title on the phone"
            />
          </label>
          <p className="eyebrow">
            Lower-case letters, digits and underscores. Choose the key carefully: it can never be
            changed, and reusing a retired one would make past sessions ambiguous.
          </p>
          <button className="button" disabled={busy === "new-topic"} type="submit">
            {busy === "new-topic" ? "Adding…" : "Add topic"}
          </button>
        </form>
      </article>

      <article className="data-card">
        <header>
          <div>
            <h3>Rating questions</h3>
            <span>
              The group representative scores each of these 1–5 at the end of a visit — not the
              agent, who would rate their own coaching.
            </span>
          </div>
        </header>
        <table className="data-table">
          <thead>
            <tr>
              <th>Question</th>
              <th>Key</th>
              <th>Status</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {data.dimensions.map((dimension) => (
              <tr key={dimension.key}>
                <td>
                  <TitleEditor
                    busy={busy === dimension.key}
                    value={dimension.title}
                    onSave={(title) =>
                      run(
                        dimension.key,
                        () =>
                          apiFetch(`/mentorship-dimensions/${dimension.key}`, {
                            method: "PATCH",
                            body: JSON.stringify({ title })
                          }).then(() => undefined),
                        "Renamed."
                      )
                    }
                  />
                </td>
                <td>
                  <code>{dimension.key}</code>
                </td>
                <td>
                  <span className={dimension.isActive ? "pill" : "pill gold"}>
                    {dimension.isActive ? "In use" : "Retired"}
                  </span>
                </td>
                <td>
                  <button
                    className="button subtle"
                    disabled={busy === dimension.key}
                    onClick={() =>
                      run(
                        dimension.key,
                        () =>
                          apiFetch(`/mentorship-dimensions/${dimension.key}`, {
                            method: "PATCH",
                            body: JSON.stringify({ isActive: !dimension.isActive })
                          }).then(() => undefined),
                        dimension.isActive ? "Retired." : "Back in use."
                      )
                    }
                  >
                    {dimension.isActive ? "Retire" : "Put back"}
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>

        <form onSubmit={addDimension}>
          <h4>Add a rating question</h4>
          <label>
            Key
            <input
              value={dimensionKey}
              disabled={busy === "new-dimension"}
              onChange={(event) =>
                setDimensionKey(event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ""))
              }
              placeholder="clarity"
              required
            />
          </label>
          <label>
            Question
            <input
              maxLength={200}
              value={dimensionTitle}
              disabled={busy === "new-dimension"}
              onChange={(event) => setDimensionTitle(event.target.value)}
              placeholder="Was the advice clear?"
              required
            />
          </label>
          <button className="button" disabled={busy === "new-dimension"} type="submit">
            {busy === "new-dimension" ? "Adding…" : "Add question"}
          </button>
        </form>
      </article>
    </section>
  );
}

/**
 * Rename in place.
 *
 * Kept as its own component so each row holds its own draft — one shared piece
 * of state would let a half-typed title leak into whichever row was clicked
 * next.
 */
function TitleEditor({
  value,
  busy,
  onSave
}: {
  value: string;
  busy: boolean;
  onSave: (title: string) => Promise<void>;
}) {
  const [draft, setDraft] = useState(value);
  const dirty = draft.trim() !== value && draft.trim().length > 0;

  return (
    <div className="button-row">
      <input
        maxLength={200}
        value={draft}
        disabled={busy}
        onChange={(event) => setDraft(event.target.value)}
      />
      <button
        className="button subtle"
        disabled={busy || !dirty}
        onClick={() => onSave(draft.trim())}
        type="button"
      >
        Rename
      </button>
    </div>
  );
}
