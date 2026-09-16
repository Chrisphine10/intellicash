import rateLimit, { type Options } from "express-rate-limit";
import type { Request } from "express";
import { normalisePhone } from "../lib/phone";

/**
 * Rate limits for the handful of routes that are worth abusing.
 *
 * Deliberately NOT global: a group recording twenty transactions during a
 * meeting is normal traffic, and throttling that would break the app's whole
 * purpose.
 *
 * Nothing here keys on IP. `trust proxy` is not configured, so behind the
 * deployment's proxy every request would carry the same address and one
 * attacker would lock out every user at once. It is also wrong on its own
 * terms here: members routinely share a handset, and a meeting runs on one
 * hotspot. Keying on the identity being acted upon throttles the abuse without
 * that blast radius.
 */

const isTest = process.env.NODE_ENV === "test";

function baseOptions(overrides: Partial<Options>): Partial<Options> {
  return {
    standardHeaders: "draft-7",
    legacyHeaders: false,
    // The suite drives these routes hard on purpose.
    skip: () => isTest,
    ...overrides
  };
}

function bodyField(req: Request, field: string) {
  const value = (req.body as Record<string, unknown> | undefined)?.[field];
  return typeof value === "string" ? value.trim().toLowerCase() : "";
}

/**
 * Password guessing, keyed on the account being tried.
 *
 * Someone hammering one account is stopped; everyone else signing in from the
 * same place is unaffected.
 */
export const loginRateLimit = rateLimit(
  baseOptions({
    windowMs: 15 * 60 * 1000,
    limit: 10,
    // Only failures count. A group's handset gets passed round a meeting and
    // several officials sign in and out on it within the hour; counting those
    // would lock out people who typed the right password every time, which is
    // a worse outcome than the guessing this is meant to stop.
    skipSuccessfulRequests: true,
    keyGenerator: (req) => {
      const phone = bodyField(req, "phone");
      const identifier = phone ? normalisePhone(phone) : bodyField(req, "email");
      // No identifier at all means the request is malformed; bucket those
      // together rather than handing out an unlimited allowance.
      return `login:${identifier || "unknown"}`;
    },
    message: {
      error: {
        code: "TOO_MANY_ATTEMPTS",
        message: "Too many sign-in attempts for this account. Wait 15 minutes and try again."
      }
    }
  })
);

/** Stops one number being used to churn out accounts. */
export const registerRateLimit = rateLimit(
  baseOptions({
    windowMs: 60 * 60 * 1000,
    limit: 5,
    keyGenerator: (req) => `register:${normalisePhone(bodyField(req, "phone")) || "unknown"}`,
    message: {
      error: {
        code: "TOO_MANY_ATTEMPTS",
        message: "Too many accounts have been created from this number. Try again later."
      }
    }
  })
);

/**
 * Asking to join a group.
 *
 * A group code is public and a wrong one answers 404 while a right one answers
 * 200, so an authenticated member could otherwise map the whole group
 * directory — and every hit also notifies that group's officials, making it a
 * notification-spam channel. Keyed per account, which is exactly who is doing
 * the asking.
 */
export const joinRequestRateLimit = rateLimit(
  baseOptions({
    windowMs: 60 * 60 * 1000,
    limit: 8,
    keyGenerator: (req) => `join:${req.user?.id ?? "anonymous"}`,
    message: {
      error: {
        code: "TOO_MANY_REQUESTS",
        message:
          "You have asked to join several groups recently. Wait a while before asking again."
      }
    }
  })
);


/**
 * Asking for a sign-in code.
 *
 * Keyed on the NUMBER, not the IP: a whole group works off one handset behind
 * one mobile carrier NAT, and an IP key would have the first group through the
 * door lock out the rest of the county.
 *
 * Successful requests count here, unlike the password limiter. Every request
 * sends a real SMS that somebody pays for, and a "success" is exactly what an
 * abuser wants to repeat.
 */
export const otpRequestRateLimit = rateLimit(
  baseOptions({
    windowMs: 60 * 60 * 1000,
    limit: 6,
    keyGenerator: (req) => {
      const phone = bodyField(req, "phone");
      return `otp-request:${phone ? normalisePhone(phone) : "unknown"}`;
    },
    message: {
      error: {
        code: "TOO_MANY_CODES",
        message: "Too many sign-in codes requested for this number. Try again in an hour."
      }
    }
  })
);

/**
 * Guessing a sign-in code.
 *
 * The service already burns a code after five wrong guesses. This is the outer
 * wall: without it, an attacker could request a fresh code and spend five
 * guesses on it, over and over.
 */
export const otpVerifyRateLimit = rateLimit(
  baseOptions({
    windowMs: 15 * 60 * 1000,
    limit: 10,
    skipSuccessfulRequests: true,
    keyGenerator: (req) => {
      const phone = bodyField(req, "phone");
      return `otp-verify:${phone ? normalisePhone(phone) : "unknown"}`;
    },
    message: {
      error: {
        code: "TOO_MANY_ATTEMPTS",
        message: "Too many attempts. Wait 15 minutes and try again."
      }
    }
  })
);
