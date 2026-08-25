"use client";

import { useEffect, useState } from "react";

import { apiFetch } from "./api";

/**
 * Whether the Intelli-Store has anything to show the public.
 *
 * The storefront is real but empty: every product on production belonged to the
 * demo programme and is now correctly hidden, so the catalogue is bare until
 * real suppliers are loaded. A navigation link is a promise that there is
 * something behind it, and the landing page was advertising "0 products" and
 * "0 field officers you can book" as though they were selling points.
 *
 * "Something to show" is products OR agents, not products alone. A programme
 * with field officers and no catalogue can still be booked, and that page is
 * worth linking to.
 *
 * The whole public site loads its data in the browser, so this does too rather
 * than introducing the first server-side API call on a page render path.
 */
export interface StoreAvailability {
  /** False until proven otherwise — see `useStoreIsLive`. */
  isLive: boolean;
  /** True while the answer is still unknown. */
  isResolving: boolean;
}

interface StoreShape {
  products: unknown[];
  agents: unknown[];
}

/**
 * One request per page load, shared by every caller.
 *
 * The header, the footer and the landing section all need this answer. Without
 * the shared promise each would fetch the same endpoint independently.
 */
let inFlight: Promise<boolean> | null = null;

export function storeIsLive(): Promise<boolean> {
  inFlight ??= apiFetch<StoreShape>("/public/intelli-store")
    .then((store) => store.products.length > 0 || store.agents.length > 0)
    // Fail closed. If the store cannot be reached the page behind the link
    // cannot load either, so offering the link would lead somewhere broken.
    .catch(() => false);

  return inFlight;
}

/** Test seam: the module-level cache would otherwise leak between cases. */
export function resetStoreAvailabilityCache() {
  inFlight = null;
}

/**
 * Starts hidden and appears once the store is confirmed to have content.
 *
 * Deliberately this way round. Rendering the link first and removing it a
 * moment later makes the navigation jump and briefly offers a dead end; this
 * way nothing is ever advertised that is not there.
 */
export function useStoreIsLive(): StoreAvailability {
  const [state, setState] = useState<StoreAvailability>({ isLive: false, isResolving: true });

  useEffect(() => {
    let mounted = true;

    storeIsLive().then((isLive) => {
      if (mounted) setState({ isLive, isResolving: false });
    });

    return () => {
      mounted = false;
    };
  }, []);

  return state;
}
