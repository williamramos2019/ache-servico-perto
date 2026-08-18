export type SortMode = "recent" | "salary_desc" | "salary_asc";
export type RemoteMode = "all" | "yes" | "no";

export type SearchState = {
  q: string;
  city: string;
  remote: RemoteMode;
  employment: string;
  experience: string;
  salaryMin: number;
  sort: SortMode;
  page: number;
};

export type SavedSearch = { name: string; params: SearchState };
