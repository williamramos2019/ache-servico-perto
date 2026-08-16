import { phpGet } from "@/lib/php-api";

export type BlogPost = {
  id: string;
  slug: string;
  title: string;
  excerpt: string | null;
  content: string | null;
  cover_url: string | null;
  author_name: string | null;
  published_at: string | null;
  meta_description: string | null;
  keywords: string[];
  meta_title: string | null;
  og_image: string | null;
};

export async function fetchBlogPosts() {
  const data = await phpGet<{ posts: BlogPost[] }>("/api/content/index.php?op=posts");
  return data.posts ?? [];
}

export async function fetchBlogPostBySlug(slug: string) {
  const data = await phpGet<{ post: BlogPost | null }>(
    `/api/content/index.php?op=post&slug=${encodeURIComponent(slug)}`,
  );
  return data.post;
}
