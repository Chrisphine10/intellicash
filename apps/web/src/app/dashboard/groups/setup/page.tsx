"use client";

import React from "react";
import Link from "next/link";
import { ArrowLeft } from "@/lib/theme-icons";
import { GroupSetupWizard } from "../../../../components/group-wizard";

export default function GroupSetupPage() {
  return (
    <>
      <section className="page-heading">
        <div>
          <p className="eyebrow">
            <Link className="inline-back" href="/dashboard/groups">
              <ArrowLeft size={17} />
              <span>Groups</span>
            </Link>
          </p>
          <h2>Quick Setup Wizard</h2>
        </div>
        <div className="page-heading-actions">
          <span className="pill">4 steps</span>
        </div>
      </section>
      <GroupSetupWizard />
    </>
  );
}
