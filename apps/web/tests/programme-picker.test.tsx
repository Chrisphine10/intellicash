import React from "react";
import { fireEvent, render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { ProgrammePicker } from "../src/components/dashboard/programme-picker";
import type { ProgrammeRow } from "../src/types/dashboard";

/**
 * A village agent may serve several programmes, all belonging to one partner.
 *
 * The interesting part is not that multi-select works — it is that the rule is
 * visible before it is broken. Letting somebody tick two partners and then
 * telling them "these belong to two partners" is a correct error message and a
 * bad interface: by then they have made the choice and have to work out which
 * tick was wrong.
 */

function programme(id: string, name: string, partnerId: string, partnerName: string) {
  return {
    id,
    name,
    country: "Kenya",
    partner: { id: partnerId, name: partnerName }
  } as unknown as ProgrammeRow;
}

const programmes = [
  programme("a1", "Coffee Cooperative", "pA", "Rainforest Alliance"),
  programme("a2", "Dairy Cooperative", "pA", "Rainforest Alliance"),
  programme("b1", "Water Access", "pB", "County Government")
];

describe("choosing an agent's programmes", () => {
  it("lets several programmes of one partner be chosen", () => {
    const onChange = vi.fn();
    render(
      <ProgrammePicker onChange={onChange} programmes={programmes} selectedIds={["a1"]} />
    );

    fireEvent.click(screen.getByRole("checkbox", { name: /Dairy Cooperative/i }));

    expect(onChange).toHaveBeenCalledWith(expect.arrayContaining(["a1", "a2"]));
  });

  it("closes off other partners as soon as one is chosen", () => {
    render(
      <ProgrammePicker onChange={vi.fn()} programmes={programmes} selectedIds={["a1"]} />
    );

    // Same partner: still available.
    expect(screen.getByRole("checkbox", { name: /Dairy Cooperative/i })).not.toBeDisabled();
    // Different partner: unavailable, and still on screen. Hiding it would
    // leave somebody hunting for a programme they know exists.
    expect(screen.getByRole("checkbox", { name: /Water Access/i })).toBeDisabled();
    expect(screen.getByText("different partner")).toBeInTheDocument();
  });

  it("opens every partner back up when the selection is cleared", () => {
    render(<ProgrammePicker onChange={vi.fn()} programmes={programmes} selectedIds={[]} />);

    for (const name of [/Coffee Cooperative/i, /Dairy Cooperative/i, /Water Access/i]) {
      expect(screen.getByRole("checkbox", { name })).not.toBeDisabled();
    }
    expect(screen.queryByText("different partner")).not.toBeInTheDocument();
  });

  it("says how many are chosen, and offers a way back", () => {
    const onChange = vi.fn();
    render(
      <ProgrammePicker onChange={onChange} programmes={programmes} selectedIds={["a1", "a2"]} />
    );

    expect(screen.getByText("2 programmes")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Clear" }));
    expect(onChange).toHaveBeenCalledWith([]);
  });

  it("says so plainly when there are no programmes to choose from", () => {
    render(<ProgrammePicker onChange={vi.fn()} programmes={[]} selectedIds={[]} />);

    // An empty box with a heading tells somebody nothing about what to do.
    expect(screen.getByText(/No programmes yet\. Create one first/i)).toBeInTheDocument();
  });
});
