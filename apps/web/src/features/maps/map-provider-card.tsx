"use client";

import { useEffect, useState } from "react";
import { apiFetch } from "../../lib/api";
import { loadMapConfig, type MapConfig } from "./group-map";

/**
 * Which map the console draws: Google Maps (needs the browser key configured
 * above) or OpenStreetMap (free, no key). Saved platform-wide; the meetings
 * map falls back to OpenStreetMap by itself if Google ever fails to load.
 */
export function MapProviderCard({ canConfigure }: { canConfigure: boolean }) {
  const [config, setConfig] = useState<MapConfig | null>(null);
  const [busy, setBusy] = useState(false);
  const [message, setMessage] = useState<{ ok: boolean; text: string } | null>(null);

  useEffect(() => {
    loadMapConfig(true).then(setConfig);
  }, []);

  async function choose(provider: MapConfig["provider"]) {
    if (!config || provider === config.provider) return;
    setBusy(true);
    setMessage(null);
    try {
      const saved = await apiFetch<MapConfig>("/integrations/map-config", {
        method: "PUT",
        body: JSON.stringify({ provider })
      });
      setConfig(saved);
      await loadMapConfig(true);
      setMessage({ ok: true, text: provider === "GOOGLE_MAPS" ? "Maps now use Google Maps." : "Maps now use OpenStreetMap." });
    } catch (error) {
      setMessage({ ok: false, text: error instanceof Error ? error.message : "Could not change the map." });
    } finally {
      setBusy(false);
    }
  }

  if (!config?.google || !config.provider) return null;
  const googleReady = config.google.configured;

  return (
    <section className="data-card">
      <header>
        <div>
          <h3>Map provider</h3>
          <p>
            The map behind group locations and meetings.
            {config.chosen ? "" : " Not chosen yet: Google Maps is used when a key is set, otherwise OpenStreetMap."}
          </p>
        </div>
      </header>
      {message ? <div className={`dashboard-notice ${message.ok ? "" : "error"}`}>{message.text}</div> : null}
      <div className="choice-cards">
        <label className={`choice-card ${config.provider === "GOOGLE_MAPS" ? "is-selected" : ""}`}>
          <input
            checked={config.provider === "GOOGLE_MAPS"}
            disabled={!canConfigure || busy || !googleReady}
            name="map-provider"
            type="radio"
            onChange={() => choose("GOOGLE_MAPS")}
          />
          <span>
            <strong>Google Maps</strong>
            <small>
              {googleReady
                ? `Uses the browser key ${config.google.source === "stored" ? "saved above" : "from the server environment"}. Falls back to OpenStreetMap if Google refuses it.`
                : "Add a Google Maps browser key (Google Maps, above) to choose this."}
            </small>
          </span>
        </label>
        <label className={`choice-card ${config.provider === "OPENSTREETMAP" ? "is-selected" : ""}`}>
          <input
            checked={config.provider === "OPENSTREETMAP"}
            disabled={!canConfigure || busy}
            name="map-provider"
            type="radio"
            onChange={() => choose("OPENSTREETMAP")}
          />
          <span>
            <strong>OpenStreetMap</strong>
            <small>Free and keyless. Map data © OpenStreetMap contributors.</small>
          </span>
        </label>
      </div>
    </section>
  );
}
