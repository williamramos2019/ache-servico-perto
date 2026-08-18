import { useQuery } from "@tanstack/react-query";
import { liveFeedApi, type LiveFeedItem } from "@/lib/domain-api";

export function useLiveFeed(opts: { city?: string; limit?: number } = {}) {
  const city = opts.city;
  const limit = opts.limit ?? 60;
  const query = useQuery({
    queryKey: ["live-feed", city, limit],
    queryFn: () => liveFeedApi.list({ city, limit }),
    staleTime: 30_000,
    refetchInterval: 30_000,
    refetchOnWindowFocus: true,
  });

  return {
    ...query,
    items: (query.data?.items ?? []) as LiveFeedItem[],
  };
}
