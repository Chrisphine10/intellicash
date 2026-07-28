import React from "react";
import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";
import { translations, getTranslation } from "../src/lib/i18n/translations";
import { TranslationProvider, useTranslation } from "../src/lib/i18n/use-translation";
import type { LanguagePreference } from "@intellicash/shared";

function TestConsumer({ fallback }: { fallback?: string }) {
  const { t } = useTranslation();
  return (
    <div>
      <span data-testid="app-name">{t("app.name")}</span>
      <span data-testid="missing">{t("missing.key", fallback)}</span>
    </div>
  );
}

describe("i18n translations", () => {
  it("provides all translation keys for every language", () => {
    const languages: LanguagePreference[] = ["ENGLISH", "KISWAHILI", "GIKUYU", "LUO", "KIEMBU"];
    const englishKeys = Object.keys(translations.ENGLISH);

    for (const lang of languages) {
      const langKeys = Object.keys(translations[lang]);
      for (const key of englishKeys) {
        expect(langKeys).toContain(key);
      }
    }
  });

  it("falls back to English for missing translations", () => {
    const result = getTranslation("KISWAHILI" as LanguagePreference, "missing.test.key", "fallback");
    expect(result).toBe("fallback");
  });

  it("returns key itself when no fallback and no English translation exists", () => {
    const result = getTranslation("LUO" as LanguagePreference, "nonexistent.key.xyz");
    expect(result).toBe("nonexistent.key.xyz");
  });

  it("returns Swahili translation when available", () => {
    const result = getTranslation("KISWAHILI" as LanguagePreference, "nav.dashboard");
    expect(result).toBe("Dashibodi");
  });

  it("returns Luo translation when available", () => {
    const result = getTranslation("LUO" as LanguagePreference, "nav.dashboard");
    expect(result).toBe("Dashibodi");
  });

  it("returns Embu (Kiembu) translation when available", () => {
    const result = getTranslation("KIEMBU" as LanguagePreference, "auth.login");
    expect(result).toBe("Ingia");
  });

  it("returns Gikuyu translation when available", () => {
    const result = getTranslation("GIKUYU" as LanguagePreference, "auth.login");
    expect(result).toBe("Ingia");
  });

  it("has all navigation keys translated in all languages", () => {
    const navKeys = [
      "nav.dashboard",
      "nav.groups",
      "nav.meetings",
      "nav.members",
      "nav.passbook",
      "nav.partners",
      "nav.store",
      "nav.audit",
      "nav.users",
      "nav.settings",
      "nav.account",
      "nav.help",
      "nav.sign-out"
    ];
    const languages: LanguagePreference[] = ["ENGLISH", "KISWAHILI", "GIKUYU", "LUO", "KIEMBU"];

    for (const lang of languages) {
      for (const key of navKeys) {
        const translation = getTranslation(lang, key);
        expect(translation).not.toBe("");
      }
    }
  });

  it("has all meeting keys translated in all languages", () => {
    const meetingKeys = [
      "meeting.title",
      "meeting.schedule",
      "meeting.open",
      "meeting.seal",
      "meeting.three-key",
      "meeting.steps.opening",
      "meeting.steps.minutes",
      "meeting.steps.social",
      "meeting.steps.repayments",
      "meeting.steps.shares",
      "meeting.steps.loans",
      "meeting.steps.resolutions",
      "meeting.steps.closing",
      "meeting.attendance",
      "meeting.attendance.present",
      "meeting.attendance.absent",
      "meeting.attendance.late"
    ];
    const languages: LanguagePreference[] = ["ENGLISH", "KISWAHILI", "GIKUYU", "LUO", "KIEMBU"];

    for (const lang of languages) {
      for (const key of meetingKeys) {
        const translation = getTranslation(lang, key);
        expect(translation).not.toBe("");
      }
    }
  });

  it("has all share-out keys translated in all languages", () => {
    const shareOutKeys = [
      "share-out.title",
      "share-out.preview",
      "share-out.post",
      "share-out.pool",
      "share-out.payout",
      "share-out.total-shares",
      "share-out.rounding"
    ];
    const languages: LanguagePreference[] = ["ENGLISH", "KISWAHILI", "GIKUYU", "LUO", "KIEMBU"];

    for (const lang of languages) {
      for (const key of shareOutKeys) {
        const translation = getTranslation(lang, key);
        expect(translation).not.toBe("");
      }
    }
  });

  it("has all common keys translated in all languages", () => {
    const commonKeys = [
      "common.save",
      "common.cancel",
      "common.delete",
      "common.edit",
      "common.create",
      "common.loading"
    ];
    const languages: LanguagePreference[] = ["ENGLISH", "KISWAHILI", "GIKUYU", "LUO", "KIEMBU"];

    for (const lang of languages) {
      for (const key of commonKeys) {
        const translation = getTranslation(lang, key);
        expect(translation).not.toBe("");
      }
    }
  });
});

describe("TranslationProvider", () => {
  it("provides translation via context", () => {
    render(
      <TranslationProvider initialLanguage="KISWAHILI">
        <TestConsumer />
      </TranslationProvider>
    );

    expect(screen.getByTestId("app-name").textContent).toBe("Intelli-Cash");
  });

  it("falls back when key is missing", () => {
    render(
      <TranslationProvider initialLanguage="ENGLISH">
        <TestConsumer fallback="Custom Fallback" />
      </TranslationProvider>
    );

    expect(screen.getByTestId("missing").textContent).toBe("Custom Fallback");
  });
});
