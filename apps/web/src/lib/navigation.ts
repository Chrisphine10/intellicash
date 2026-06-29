import {
  Activity,
  BookOpenText,
  Bot,
  BarChart3,
  ClipboardList,
  FileText,
  FolderKanban,
  Landmark,
  LockKeyhole,
  Megaphone,
  Settings,
  ShieldCheck,
  ShoppingBag,
  UserCog,
  UsersRound,
  WalletCards
} from "@/lib/theme-icons";
import type { Role } from "@intellicash/shared";

export const navigationSections = [
  { key: "main", label: "Main" },
  { key: "work", label: "Work" },
  { key: "review", label: "Review" },
  { key: "setup", label: "Setup" }
] as const;

type NavigationSectionKey = (typeof navigationSections)[number]["key"];
type NavigationPriority = Partial<Record<Role, number>> & { default: number };

export interface NavigationItem {
  label: string;
  labelByRole?: Partial<Record<Role, string>>;
  href: string;
  icon: typeof BarChart3;
  roles: Role[];
  section: NavigationSectionKey;
  priority: NavigationPriority;
}

const allRoles: Role[] = ["IWL_ADMIN", "PARTNER_OFFICER", "GROUP_ACCOUNT", "MEMBER", "LENDER", "READ_ONLY"];

export const navigationItems: NavigationItem[] = [
  {
    label: "Dashboard",
    href: "/dashboard",
    icon: BarChart3,
    roles: allRoles,
    section: "main",
    priority: { default: 0 }
  },
  {
    label: "Passbook",
    href: "/dashboard/passbook",
    icon: BookOpenText,
    roles: ["MEMBER"],
    section: "main",
    priority: { default: 10, MEMBER: 10 }
  },
  {
    label: "Meetings",
    href: "/dashboard/meetings",
    icon: Activity,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "GROUP_ACCOUNT", "MEMBER", "READ_ONLY"],
    section: "work",
    priority: { default: 30, IWL_ADMIN: 30, PARTNER_OFFICER: 30, GROUP_ACCOUNT: 10, MEMBER: 20, READ_ONLY: 40 }
  },
  {
    label: "Groups",
    labelByRole: { GROUP_ACCOUNT: "My Group" },
    href: "/dashboard/groups",
    icon: UsersRound,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "GROUP_ACCOUNT", "LENDER", "READ_ONLY"],
    section: "work",
    priority: { default: 20, IWL_ADMIN: 10, PARTNER_OFFICER: 20, GROUP_ACCOUNT: 5, LENDER: 20, READ_ONLY: 30 }
  },
  {
    label: "Programs",
    href: "/dashboard/programmes",
    icon: FolderKanban,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "MEMBER", "LENDER", "READ_ONLY"],
    section: "work",
    priority: { default: 20, IWL_ADMIN: 20, PARTNER_OFFICER: 10, MEMBER: 40, LENDER: 10, READ_ONLY: 20 }
  },
  {
    label: "Intelli-Store",
    href: "/dashboard/intelli-store",
    icon: ShoppingBag,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "GROUP_ACCOUNT", "MEMBER", "LENDER", "READ_ONLY"],
    section: "work",
    priority: { default: 60, IWL_ADMIN: 60, PARTNER_OFFICER: 60, GROUP_ACCOUNT: 20, MEMBER: 30, LENDER: 30, READ_ONLY: 70 }
  },
  {
    label: "Partners",
    href: "/dashboard/partners",
    icon: Landmark,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "READ_ONLY"],
    section: "work",
    priority: { default: 40, IWL_ADMIN: 40, PARTNER_OFFICER: 50, READ_ONLY: 50 }
  },
  {
    label: "VA / CBT",
    href: "/dashboard/agents",
    icon: ShieldCheck,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "READ_ONLY"],
    section: "work",
    priority: { default: 50, IWL_ADMIN: 50, PARTNER_OFFICER: 40, READ_ONLY: 60 }
  },
  {
    label: "Reports",
    href: "/dashboard/reports",
    icon: ClipboardList,
    roles: ["IWL_ADMIN", "PARTNER_OFFICER", "GROUP_ACCOUNT", "LENDER", "READ_ONLY"],
    section: "review",
    priority: { default: 70, IWL_ADMIN: 70, PARTNER_OFFICER: 70, GROUP_ACCOUNT: 30, LENDER: 40, READ_ONLY: 10 }
  },
  {
    label: "IntelliAudit",
    href: "/dashboard/intelliaudit",
    icon: Bot,
    roles: ["IWL_ADMIN"],
    section: "review",
    priority: { default: 80, IWL_ADMIN: 80 }
  },
  {
    label: "Audit",
    href: "/dashboard/audit",
    icon: FileText,
    roles: ["IWL_ADMIN"],
    section: "review",
    priority: { default: 90, IWL_ADMIN: 90 }
  },
  {
    label: "Payments",
    href: "/dashboard/payments",
    icon: WalletCards,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 10, IWL_ADMIN: 10 }
  },
  {
    label: "Users",
    href: "/dashboard/users",
    icon: UserCog,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 20, IWL_ADMIN: 20 }
  },
  {
    label: "SMS",
    href: "/dashboard/sms",
    icon: Megaphone,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 25, IWL_ADMIN: 25 }
  },
  {
    label: "Integrations",
    href: "/dashboard/integrations",
    icon: LockKeyhole,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 30, IWL_ADMIN: 30 }
  },
  {
    label: "API Docs",
    href: "/dashboard/api-docs",
    icon: BookOpenText,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 40, IWL_ADMIN: 40 }
  },
  {
    label: "Settings",
    href: "/dashboard/settings",
    icon: Settings,
    roles: ["IWL_ADMIN"],
    section: "setup",
    priority: { default: 50, IWL_ADMIN: 50 }
  }
];

export function getNavigationItemsForRole(role?: string | null) {
  const roleKey = allRoles.find((candidate) => candidate === role);
  const items = navigationItems
    .filter((item) => (roleKey ? item.roles.includes(roleKey) : item.href === "/dashboard"))
    .map((item) =>
      roleKey && item.labelByRole?.[roleKey] ? { ...item, label: item.labelByRole[roleKey] } : item
    );

  return [...items].sort((left, right) => {
    const leftSection = navigationSections.findIndex((section) => section.key === left.section);
    const rightSection = navigationSections.findIndex((section) => section.key === right.section);
    if (leftSection !== rightSection) return leftSection - rightSection;

    const leftPriority = roleKey ? left.priority[roleKey] ?? left.priority.default : left.priority.default;
    const rightPriority = roleKey ? right.priority[roleKey] ?? right.priority.default : right.priority.default;
    if (leftPriority !== rightPriority) return leftPriority - rightPriority;

    return left.label.localeCompare(right.label);
  });
}
