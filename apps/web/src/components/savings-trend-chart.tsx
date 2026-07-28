"use client";

import React from "react";

interface SavingsTrendChartProps {
  data: Array<{ label: string; savingsCents: number }>;
}

export function SavingsTrendChart({ data }: SavingsTrendChartProps) {
  if (!data || data.length === 0) {
    return (
      <div className="stat-card">
        <h3>Savings Trend</h3>
        <p className="muted">No savings data yet</p>
      </div>
    );
  }

  const maxCents = Math.max(...data.map((d) => d.savingsCents), 1);
  const barCount = data.length;

  return (
    <div className="stat-card savings-trend-chart">
      <h3>Savings Trend</h3>
      <div className="savings-chart-bars">
        {data.map((point, i) => {
          const heightPct = (point.savingsCents / maxCents) * 100;
          return (
            <div key={i} className="savings-chart-bar-group" title={`${point.label}: KSh ${(point.savingsCents / 100).toLocaleString()}`}>
              <div className="savings-chart-bar" style={{ height: `${Math.max(heightPct, 2)}%` }} />
              <span className="savings-chart-label">{point.label}</span>
            </div>
          );
        })}
      </div>
    </div>
  );
}
