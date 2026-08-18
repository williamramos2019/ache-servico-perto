import { describe, expect, it } from "vitest";
import { DEFAULT_SEARCH, parseSearchParams } from "../src/features/jobs";

describe("jobs search from remix UI", () => {
  it("keeps supported filters and clamps invalid values", () => {
    expect(
      parseSearchParams({
        q: "auxiliar",
        city: "Vespasiano",
        remote: "yes",
        employment: "CLT",
        experience: "Pleno",
        salaryMin: "2500",
        sort: "salary_desc",
        page: "3",
      }),
    ).toEqual({
      q: "auxiliar",
      city: "Vespasiano",
      remote: "yes",
      employment: "CLT",
      experience: "Pleno",
      salaryMin: 2500,
      sort: "salary_desc",
      page: 3,
    });
  });

  it("falls back to defaults for unknown or empty params", () => {
    expect(parseSearchParams({ remote: "maybe", sort: "popular", page: "-2", salaryMin: "0" })).toEqual(
      DEFAULT_SEARCH,
    );
  });
});
