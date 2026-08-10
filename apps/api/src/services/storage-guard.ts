import { statfs } from "node:fs/promises";
import { uploadRoot } from "../lib/uploads";

/**
 * Refuses uploads before the disk fills.
 *
 * Field evidence changes the arithmetic on this box: roughly 15 photos per
 * visit at ~400 KB is about 6 MB a visit, and sixty agents visiting twenty-five
 * groups monthly is on the order of 9 GB a month. The SQLite database lives on
 * the same volume, and a full disk does not politely stop accepting photographs
 * — it corrupts the ledger mid-write.
 *
 * So there are two thresholds, and they are deliberately different in kind:
 *
 *  - **Below 15% free** uploads are refused with a *retryable* 503. The phone
 *    keeps the file, the visit itself still syncs, and the photo arrives once
 *    somebody clears space. A late photograph costs nothing.
 *  - **Below 5% free** it is an emergency. Still refused, still 503, but flagged
 *    CRITICAL so it can be alerted on rather than absorbed silently.
 *
 * Note what is *not* guarded: only uploads. A visit, a meeting, or a ledger
 * entry is a few hundred bytes and must never be blocked to save room for an
 * image.
 */

/** Below this fraction free, stop accepting uploads. */
export const STORAGE_LOW_FRACTION = 0.15;

/** Below this, the database itself is at risk. */
export const STORAGE_CRITICAL_FRACTION = 0.05;

export type StorageLevel = "OK" | "LOW" | "CRITICAL";

export interface StorageVerdict {
  level: StorageLevel;
  /** 0-1. */
  freeFraction: number;
  freeBytes: number;
  totalBytes: number;
  /** False when uploads must be refused. */
  acceptsUploads: boolean;
  message: string;
}

/**
 * The decision, with no I/O — so the thresholds can be tested without a full
 * disk, which is not a state any test suite should have to arrange.
 *
 * A total of zero (an unreadable filesystem) is treated as OK rather than
 * CRITICAL: failing to measure the disk is not evidence that it is full, and
 * refusing every upload because `statfs` misbehaved would be a self-inflicted
 * outage.
 */
export function judgeStorage(freeBytes: number, totalBytes: number): StorageVerdict {
  if (!Number.isFinite(freeBytes) || !Number.isFinite(totalBytes) || totalBytes <= 0) {
    return {
      level: "OK",
      freeFraction: 1,
      freeBytes: 0,
      totalBytes: 0,
      acceptsUploads: true,
      message: "Disk usage could not be measured; uploads allowed."
    };
  }

  const freeFraction = Math.max(0, Math.min(1, freeBytes / totalBytes));
  const percent = (freeFraction * 100).toFixed(1);

  if (freeFraction < STORAGE_CRITICAL_FRACTION) {
    return {
      level: "CRITICAL",
      freeFraction,
      freeBytes,
      totalBytes,
      acceptsUploads: false,
      message: `Only ${percent}% of disk remains. Uploads are stopped to protect the database.`
    };
  }

  if (freeFraction < STORAGE_LOW_FRACTION) {
    return {
      level: "LOW",
      freeFraction,
      freeBytes,
      totalBytes,
      acceptsUploads: false,
      message: `Only ${percent}% of disk remains. Photos will be accepted again once space is freed.`
    };
  }

  return {
    level: "OK",
    freeFraction,
    freeBytes,
    totalBytes,
    acceptsUploads: true,
    message: `${percent}% of disk free.`
  };
}

/**
 * Measures the volume holding the uploads.
 *
 * Never throws: a filesystem that cannot be interrogated must not take the API
 * down. See `judgeStorage` for why an unmeasurable disk is treated as fine.
 */
export async function checkUploadStorage(path = uploadRoot): Promise<StorageVerdict> {
  try {
    const stats = await statfs(path);
    // bavail, not bfree: the blocks an unprivileged process can actually use.
    // The reserved-for-root margin is not space this service may spend.
    const free = Number(stats.bavail) * Number(stats.bsize);
    const total = Number(stats.blocks) * Number(stats.bsize);
    return judgeStorage(free, total);
  } catch {
    return judgeStorage(0, 0);
  }
}
