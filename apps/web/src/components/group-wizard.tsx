"use client";

import type { FormEvent } from "react";
import React, { useState } from "react";
import { useRouter } from "next/navigation";
import { ArrowLeft, ArrowRight, Banknote, CheckCircle2, Database, HandCoins, UsersRound } from "@/lib/theme-icons";
import { apiFetch, formatKes, humanizeEnum } from "../lib/api";

type WizardStep = "basics" | "savings" | "loans" | "schedule";

interface WizardData {
  name: string;
  code: string;
  county: string;
  subCounty: string;
  meetingDay: string;
  shareValueCents: number;
  maxSharesPerMemberPerMeeting: number;
  socialFundCents: number;
  interestRateBps: number;
  maxLoanMultiplier: number;
  loanTermMonths: number;
  meetingFrequency: "WEEKLY" | "BIWEEKLY" | "MONTHLY";
  programmeIds: string[];
}

const defaultWizardData: WizardData = {
  name: "",
  code: "",
  county: "Kiambu",
  subCounty: "",
  meetingDay: "Sunday",
  shareValueCents: 10000,
  maxSharesPerMemberPerMeeting: 10,
  socialFundCents: 5000,
  interestRateBps: 500,
  maxLoanMultiplier: 2,
  loanTermMonths: 3,
  meetingFrequency: "WEEKLY",
  programmeIds: []
};

const steps: { key: WizardStep; label: string; icon: React.ReactNode }[] = [
  { key: "basics", label: "Basics", icon: <UsersRound size={18} /> },
  { key: "savings", label: "Savings Config", icon: <Database size={18} /> },
  { key: "loans", label: "Loan Config", icon: <HandCoins size={18} /> },
  { key: "schedule", label: "Meeting Schedule", icon: <Banknote size={18} /> }
];

export function GroupSetupWizard({ programmeIds }: { programmeIds?: string[] }) {
  const router = useRouter();
  const [step, setStep] = useState<WizardStep>("basics");
  const [data, setData] = useState<WizardData>({ ...defaultWizardData, programmeIds: programmeIds ?? [] });
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);

  const stepIndex = steps.findIndex((s) => s.key === step);
  const isLast = stepIndex === steps.length - 1;
  const isFirst = stepIndex === 0;

  function updateField<K extends keyof WizardData>(field: K, value: WizardData[K]) {
    setData((prev) => ({ ...prev, [field]: value }));
  }

  async function handleNext() {
    if (step === "basics") {
      if (!data.name.trim()) { setError("Group name is required"); return; }
      if (!data.code.trim()) { setError("Group code is required"); return; }
    }
    setError(null);
    if (isLast) {
      await submitWizard();
    } else {
      setStep(steps[stepIndex + 1]!.key);
    }
  }

  function handleBack() {
    setError(null);
    if (!isFirst) setStep(steps[stepIndex - 1]!.key);
  }

  async function submitWizard() {
    setSaving(true);
    setError(null);
    try {
      await apiFetch("/groups", {
        method: "POST",
        body: JSON.stringify({
          name: data.name,
          code: data.code,
          county: data.county,
          subCounty: data.subCounty,
          meetingDay: data.meetingDay,
          shareValueCents: data.shareValueCents,
          maxSharesPerMemberPerMeeting: data.maxSharesPerMemberPerMeeting,
          programmeIds: data.programmeIds,
          cycleNumber: 1
        })
      });
      setSuccess("Group created successfully!");
      setTimeout(() => router.push("/dashboard/groups"), 1500);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Failed to create group");
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="group-wizard">
      <div className="wizard-steps">
        {steps.map((s, i) => (
          <div key={s.key} className={`wizard-step ${step === s.key ? "active" : i < stepIndex ? "done" : ""}`}>
            <span className="wizard-step-icon">
              {i < stepIndex ? <CheckCircle2 size={16} /> : s.icon}
            </span>
            <span className="wizard-step-label">{s.label}</span>
          </div>
        ))}
      </div>

      <div className="wizard-body">
        {error ? <div className="error">{error}</div> : null}
        {success ? <div className="notice success">{success}</div> : null}

        {step === "basics" && (
          <div className="wizard-form">
            <h3>Group Basics</h3>
            <label>Group Name
              <input value={data.name} onChange={(e) => updateField("name", e.target.value)} placeholder="e.g. Tujijenge Women VSLA" />
            </label>
            <label>Group Code
              <input value={data.code} onChange={(e) => updateField("code", e.target.value)} placeholder="e.g. IWL-KBU-0001" />
            </label>
            <label>County
              <input value={data.county} onChange={(e) => updateField("county", e.target.value)} />
            </label>
            <label>Sub-County
              <input value={data.subCounty} onChange={(e) => updateField("subCounty", e.target.value)} />
            </label>
          </div>
        )}

        {step === "savings" && (
          <div className="wizard-form">
            <h3>Savings Config</h3>
            <label>Share Value (KSh per share)
              <input type="number" value={data.shareValueCents / 100} onChange={(e) => updateField("shareValueCents", Number(e.target.value) * 100)} />
            </label>
            <label>Max Shares Per Member Per Meeting
              <input type="number" value={data.maxSharesPerMemberPerMeeting} onChange={(e) => updateField("maxSharesPerMemberPerMeeting", Number(e.target.value))} />
            </label>
            <p className="wizard-hint">Max per meeting: {formatKes(data.shareValueCents * data.maxSharesPerMemberPerMeeting)}</p>
            <label>Social Fund (KSh per member per meeting)
              <input type="number" value={data.socialFundCents / 100} onChange={(e) => updateField("socialFundCents", Number(e.target.value) * 100)} />
            </label>
          </div>
        )}

        {step === "loans" && (
          <div className="wizard-form">
            <h3>Loan Config</h3>
            <label>Interest Rate (%)
              <input type="number" value={data.interestRateBps / 100} onChange={(e) => updateField("interestRateBps", Number(e.target.value) * 100)} />
            </label>
            <label>Max Loan Multiplier (x savings)
              <input type="number" value={data.maxLoanMultiplier} onChange={(e) => updateField("maxLoanMultiplier", Number(e.target.value))} />
            </label>
            <p className="wizard-hint">A member with KSh 3,300 savings can borrow up to {formatKes(3300 * data.maxLoanMultiplier * 100)}</p>
            <label>Loan Term (months)
              <input type="number" value={data.loanTermMonths} onChange={(e) => updateField("loanTermMonths", Number(e.target.value))} />
            </label>
            <p className="wizard-hint">Reducing balance interest at {data.interestRateBps / 100}% per cycle</p>
          </div>
        )}

        {step === "schedule" && (
          <div className="wizard-form">
            <h3>Meeting Schedule</h3>
            <label>Meeting Frequency
              <select value={data.meetingFrequency} onChange={(e) => updateField("meetingFrequency", e.target.value as WizardData["meetingFrequency"])}>
                <option value="WEEKLY">Weekly</option>
                <option value="BIWEEKLY">Bi-weekly</option>
                <option value="MONTHLY">Monthly</option>
              </select>
            </label>
            <label>Meeting Day
              <select value={data.meetingDay} onChange={(e) => updateField("meetingDay", e.target.value)}>
                {["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"].map((day) => (
                  <option key={day} value={day}>{day}</option>
                ))}
              </select>
            </label>
          </div>
        )}
      </div>

      <div className="wizard-footer">
        <button className="button secondary" disabled={isFirst || saving} onClick={handleBack} type="button">
          <ArrowLeft size={16} /> Back
        </button>
        <button className="button" disabled={saving} onClick={handleNext} type="button">
          {isLast ? (saving ? "Creating..." : "Create Group") : "Next"} <ArrowRight size={16} />
        </button>
      </div>
    </div>
  );
}
