import { useSyncExternalStore } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { fetchCurrentUser, onPhpAuthChange } from "@/lib/php-auth";
import { phpGet, phpPost } from "@/lib/php-api";

let _userId: string | null = null;
let _authReady = false;
let _initialized = false;
const _listeners = new Set<() => void>();

function notify() {
  _listeners.forEach((l) => l());
}

function ensureInit() {
  if (_initialized) return;
  if (typeof window === "undefined") return;
  _initialized = true;
  fetchCurrentUser()
    .then((user) => {
      _userId = user?.id ?? null;
      _authReady = true;
      notify();
    })
    .catch(() => {
      _authReady = true;
      notify();
    });
  onPhpAuthChange(() => {
    fetchCurrentUser()
      .then((user) => {
        _userId = user?.id ?? null;
        _authReady = true;
        notify();
      })
      .catch(() => {
        _authReady = true;
        notify();
      });
  });
}

function subscribe(cb: () => void) {
  ensureInit();
  _listeners.add(cb);
  return () => {
    _listeners.delete(cb);
  };
}

export function useCurrentUserId() {
  return useSyncExternalStore(subscribe, () => _userId, () => null);
}

export function useAuthReady() {
  return useSyncExternalStore(subscribe, () => _authReady, () => false);
}

type FavoriteRow = {
  company_id: string;
  created_at: string;
  companies: {
    id: string;
    slug: string;
    name: string;
    tagline: string | null;
    banner_url: string | null;
    logo_url: string | null;
    plan: string | null;
    featured: boolean | null;
    cities: { name: string; slug: string } | null;
  };
};

export function useFavorites() {
  const userId = useCurrentUserId();
  return useQuery({
    queryKey: ["favorites", userId],
    enabled: !!userId,
    staleTime: 60_000,
    queryFn: async () => {
      const data = await phpGet<{ favorites: FavoriteRow[] }>("/api/favorites/index.php");
      return data.favorites ?? [];
    },
  });
}

function useFavoriteIds() {
  const userId = useCurrentUserId();
  return useQuery({
    queryKey: ["favorite-ids", userId],
    enabled: !!userId,
    staleTime: 60_000,
    queryFn: async () => {
      const data = await phpGet<{ company_ids: string[] }>("/api/favorites/index.php");
      return new Set(data.company_ids ?? []);
    },
  });
}

export function useToggleFavorite(companyId: string) {
  const qc = useQueryClient();
  const userId = useCurrentUserId();
  const ids = useFavoriteIds();
  const isFav = !!ids.data?.has(companyId);

  const mutation = useMutation({
    mutationFn: async () => {
      if (!userId) throw new Error("auth");
      const data = await phpPost<{ favorited: boolean }>("/api/favorites/index.php", {
        company_id: companyId,
        op: isFav ? "remove" : "add",
      });
      return data.favorited;
    },
    onMutate: async () => {
      await qc.cancelQueries({ queryKey: ["favorite-ids", userId] });
      const prev = qc.getQueryData<Set<string>>(["favorite-ids", userId]);
      const next = new Set(prev ?? []);
      if (isFav) next.delete(companyId);
      else next.add(companyId);
      qc.setQueryData(["favorite-ids", userId], next);
      return { prev };
    },
    onError: (_e, _v, ctx) => {
      if (ctx?.prev) qc.setQueryData(["favorite-ids", userId], ctx.prev);
    },
    onSettled: () => {
      qc.invalidateQueries({ queryKey: ["favorite-ids", userId] });
      qc.invalidateQueries({ queryKey: ["favorites", userId] });
    },
  });

  return { isFav, isLoggedIn: !!userId, toggle: mutation.mutate, isPending: mutation.isPending };
}
