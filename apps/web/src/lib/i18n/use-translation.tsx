"use client";

import React, { createContext, useCallback, useContext, useState } from "react";
import type { LanguagePreference } from "@intellicash/shared";
import { getTranslation } from "./translations";

interface TranslationContextValue {
  language: LanguagePreference;
  setLanguage: (lang: LanguagePreference) => void;
  t: (key: string, fallback?: string) => string;
}

const TranslationContext = createContext<TranslationContextValue | null>(null);

export function TranslationProvider({
  children,
  initialLanguage = "ENGLISH"
}: {
  children: React.ReactNode;
  initialLanguage?: LanguagePreference;
}) {
  const [language, setLanguage] = useState<LanguagePreference>(initialLanguage);

  const t = useCallback(
    (key: string, fallback?: string) => getTranslation(language, key, fallback),
    [language]
  );

  return (
    <TranslationContext.Provider value={{ language: language, setLanguage: setLanguage, t: t }}>
      {children}
    </TranslationContext.Provider>
  );
}

export function useTranslation(): TranslationContextValue {
  const ctx = useContext(TranslationContext);
  if (!ctx) {
    return {
      language: "ENGLISH",
      setLanguage: () => {},
      t: (key: string, fallback?: string) => fallback ?? key
    };
  }
  return ctx;
}
