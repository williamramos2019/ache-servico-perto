import { ROLE_LABEL } from "./constants";

export function getInitials(name: string): string {
  return name
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() ?? "")
    .join("");
}

export function formatRoleParty(role: string, party: string | null | undefined): string {
  const label = ROLE_LABEL[role] ?? role.replaceAll("_", " ");
  return party ? `${label} · ${party}` : label;
}
