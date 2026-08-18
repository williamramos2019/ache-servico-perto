import { describe, expect, it } from "vitest";
import { getActiveTheme, isThemeActive, SEASONAL_THEMES } from "../src/lib/seasonalThemes";

function utcDate(year: number, month: number, day: number): Date {
  return new Date(Date.UTC(year, month - 1, day, 12, 0, 0));
}

describe("seasonal themes from close-by-helper", () => {
  it("activates Natal in December and wraps Ano Novo across year end", () => {
    expect(getActiveTheme(utcDate(2026, 12, 25))?.id).toBe("natal");
    expect(getActiveTheme(utcDate(2027, 1, 1))?.id).toBe("ano-novo");
    expect(getActiveTheme(utcDate(2026, 7, 20))).toBeNull();
  });

  it("activates Dia das Mães around the 2nd Sunday of May", () => {
    const mothers = SEASONAL_THEMES.find((theme) => theme.id === "dia-das-maes");
    expect(mothers).toBeTruthy();
    expect(isThemeActive(mothers!, utcDate(2026, 4, 30))).toBe(true);
    expect(isThemeActive(mothers!, utcDate(2026, 5, 10))).toBe(true);
    expect(isThemeActive(mothers!, utcDate(2026, 5, 12))).toBe(false);
  });

  it("lets Halloween win over later overlapping windows because order is first-match", () => {
    expect(getActiveTheme(utcDate(2026, 10, 31))?.id).toBe("halloween");
  });
});
