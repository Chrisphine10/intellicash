/**
 * The single source of truth for how people reach Intelli-Cash.
 *
 * These were previously hardcoded in four separate files, so changing the
 * support number missed three of them — including the privacy page, which is
 * the number people must use to exercise data-protection rights. Import from
 * here rather than typing a number into a page.
 */

/** Digits only, international format — the canonical stored form. */
export const SUPPORT_PHONE_E164 = "254768706799";

/** Display form. */
export const SUPPORT_PHONE = "+254 768 706 799";

/** For `href` on a call link. */
export const SUPPORT_PHONE_HREF = `tel:+${SUPPORT_PHONE_E164}`;

export const SUPPORT_EMAIL = "support@intellicash.co.ke";

export const SUPPORT_EMAIL_HREF = `mailto:${SUPPORT_EMAIL}`;

/** Prefilled subject lines used by more than one page. */
export const FIELD_SUPPORT_EMAIL_HREF = `${SUPPORT_EMAIL_HREF}?subject=Field%20support%20request`;
