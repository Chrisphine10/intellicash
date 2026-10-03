"use client";

import "leaflet/dist/leaflet.css";
import { useEffect, useRef, useState } from "react";
import type { LayerGroup, Map as LeafletMap } from "leaflet";
import { apiFetch } from "../../lib/api";
import { GoogleGroupMap } from "../meetings/components";
import { escapeHtml, markerColor, type MapPin } from "../meetings/model";

export interface MapConfig {
  provider: "GOOGLE_MAPS" | "OPENSTREETMAP";
  chosen: "GOOGLE_MAPS" | "OPENSTREETMAP" | null;
  google: { configured: boolean; apiKey: string | null; source: "stored" | "env" | "none" };
  openStreetMap: { tileUrl: string; attribution: string; maxZoom: number };
}

const OSM_DEFAULTS: MapConfig["openStreetMap"] = {
  tileUrl: "https://tile.openstreetmap.org/{z}/{x}/{y}.png",
  attribution: "© OpenStreetMap contributors",
  maxZoom: 19
};

let configRequest: Promise<MapConfig | null> | null = null;

/** The map an admin chose (Integrations → Map provider), fetched once per page load. */
export function loadMapConfig(refresh = false) {
  if (refresh || !configRequest) {
    configRequest = apiFetch<MapConfig>("/integrations/map-config").catch(() => null);
  }
  return configRequest;
}

declare global {
  interface Window {
    /** Google calls this when it refuses the key (wrong website restriction, billing). */
    gm_authFailure?: () => void;
  }
}

/**
 * The group map, on whichever provider the platform uses.
 *
 * Google Maps when an admin chose it and a key is configured; OpenStreetMap
 * otherwise. If Google fails at runtime — the script will not load, or Google
 * refuses the key — the same pins are drawn on OpenStreetMap with a note, so
 * a misconfigured key costs the admin a warning, not the map.
 */
export function GroupMap({ pins }: { pins: MapPin[] }) {
  const [config, setConfig] = useState<MapConfig | null>(null);
  const [googleFailed, setGoogleFailed] = useState<string | null>(null);

  useEffect(() => {
    let live = true;
    loadMapConfig().then((value) => {
      if (live) setConfig(value);
    });
    return () => {
      live = false;
    };
  }, []);

  useEffect(() => {
    window.gm_authFailure = () =>
      setGoogleFailed("Google refused the Maps key — check that its website restrictions include this domain.");
    return () => {
      window.gm_authFailure = undefined;
    };
  }, []);

  const useGoogle = config?.provider === "GOOGLE_MAPS" && Boolean(config.google.apiKey) && !googleFailed;
  if (useGoogle) {
    return <GoogleGroupMap apiKey={config!.google.apiKey!} onFailure={setGoogleFailed} pins={pins} />;
  }
  return (
    <OpenStreetGroupMap
      note={googleFailed ? `${googleFailed} Showing OpenStreetMap instead.` : null}
      pins={pins}
      tiles={config?.openStreetMap ?? OSM_DEFAULTS}
    />
  );
}

const STATUS_LABEL: Record<MapPin["status"], string> = {
  live: "Live session",
  meeting: "Has meeting",
  group: "Group cluster"
};

/** OpenStreetMap through Leaflet. Needs no key: its tiles are public images. */
export function OpenStreetGroupMap({
  pins,
  tiles,
  note
}: {
  pins: MapPin[];
  tiles: MapConfig["openStreetMap"];
  note?: string | null;
}) {
  const elementRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<LeafletMap | null>(null);
  const layerRef = useRef<LayerGroup | null>(null);
  const leafletRef = useRef<typeof import("leaflet") | null>(null);
  const [ready, setReady] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // The map itself: created once per tile source, not on every pin change —
  // recreating it on the same element is what Leaflet calls "container is
  // being reused".
  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        // Leaflet touches `window` on import, so it loads in the browser only.
        const L = (await import("leaflet")).default;
        const element = elementRef.current;
        if (cancelled || !element) return;
        leafletRef.current = L;
        const map = L.map(element, { center: [-0.4, 37.2], zoom: 6, scrollWheelZoom: false });
        L.tileLayer(tiles.tileUrl, { attribution: tiles.attribution, maxZoom: tiles.maxZoom }).addTo(map);
        mapRef.current = map;
        layerRef.current = L.layerGroup().addTo(map);
        setReady(true);
      } catch (mapError) {
        if (!cancelled) setError(mapError instanceof Error ? mapError.message : "The map could not be drawn.");
      }
    })();
    return () => {
      cancelled = true;
      try {
        mapRef.current?.remove();
      } catch {
        // A half-drawn map can fail to tear down; nothing is left to clean.
      }
      mapRef.current = null;
      layerRef.current = null;
      setReady(false);
    };
  }, [tiles.tileUrl, tiles.attribution, tiles.maxZoom]);

  // The pins: redrawn into the existing map.
  useEffect(() => {
    const L = leafletRef.current;
    const map = mapRef.current;
    const layer = layerRef.current;
    if (!ready || !L || !map || !layer) return;
    try {
      layer.clearLayers();
      const points: Array<[number, number]> = [];
      for (const pin of pins) {
        const point: [number, number] = [pin.latitude, pin.longitude];
        points.push(point);
        L.circleMarker(point, {
          radius: pin.exact ? 8 : 11,
          color: "#ffffff",
          weight: 2,
          fillColor: markerColor(pin.status),
          fillOpacity: 1
        })
          .bindTooltip(pin.count > 1 ? String(pin.count) : "", {
            permanent: pin.count > 1,
            direction: "center",
            className: "osm-pin-count"
          })
          .bindPopup(
            `<div class="google-map-info"><strong>${escapeHtml(pin.label)}</strong><span>${escapeHtml(pin.detail)}</span>` +
              `<em>${escapeHtml(STATUS_LABEL[pin.status])}${pin.liveCount > 0 ? `, ${pin.liveCount} live` : ""}</em></div>`
          )
          .addTo(layer);
      }
      if (points.length === 1) map.setView(points[0]!, 13);
      else if (points.length > 1) map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
    } catch (mapError) {
      setError(mapError instanceof Error ? mapError.message : "The map pins could not be drawn.");
    }
  }, [pins, ready]);

  return (
    <div className="google-map-shell">
      <div aria-label="Map of group locations" className="google-group-map osm-group-map" ref={elementRef} />
      {note ? <div className="map-provider-note">{note}</div> : null}
      {error ? <div className="map-status-overlay">{error}</div> : null}
    </div>
  );
}
