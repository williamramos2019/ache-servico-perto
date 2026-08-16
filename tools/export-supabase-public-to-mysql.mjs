import fs from "node:fs";
import path from "node:path";
import { execFileSync } from "node:child_process";

const env = Object.fromEntries(
  fs
    .readFileSync(".env", "utf8")
    .split(/\r?\n/)
    .filter((l) => l && !l.startsWith("#") && l.includes("="))
    .map((l) => {
      const i = l.indexOf("=");
      let k = l.slice(0, i).trim();
      let v = l.slice(i + 1).trim();
      if (
        (v.startsWith('"') && v.endsWith('"')) ||
        (v.startsWith("'") && v.endsWith("'"))
      ) {
        v = v.slice(1, -1);
      }
      return [k, v];
    }),
);

const url = env.SUPABASE_URL;
const key = env.SUPABASE_PUBLISHABLE_KEY;
if (!url || !key) {
  console.error("missing supabase env");
  process.exit(1);
}

const PAGE = 1000;
const BATCH = 40;
const STUB_HASH = execFileSync(
  "php",
  ["-r", "echo password_hash('__NO_LOGIN__', PASSWORD_BCRYPT);"],
  { encoding: "utf8" },
).trim();

function sqlString(value) {
  return `'${String(value).replace(/\\/g, "\\\\").replace(/'/g, "''")}'`;
}

function sqlJson(value) {
  if (value === null || value === undefined) {
    return "NULL";
  }
  return sqlString(JSON.stringify(value));
}

function sqlTs(value) {
  if (!value) {
    return "NULL";
  }
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) {
    return "NULL";
  }
  const iso = d.toISOString();
  return sqlString(iso.slice(0, 23).replace("T", " "));
}

function sqlBool(value, fallback = 0) {
  if (value === null || value === undefined) {
    return String(fallback);
  }
  return value ? "1" : "0";
}

function sqlNum(value) {
  if (value === null || value === undefined || value === "") {
    return "NULL";
  }
  const n = Number(value);
  return Number.isFinite(n) ? String(n) : "NULL";
}

function sqlText(value) {
  if (value === null || value === undefined || value === "") {
    return "NULL";
  }
  return sqlString(value);
}

function nowTs() {
  return sqlTs(new Date().toISOString());
}

async function fetchAll(table, order = "id") {
  const rows = [];
  let from = 0;
  while (true) {
    const to = from + PAGE - 1;
    const res = await fetch(`${url}/rest/v1/${table}?select=*&order=${order}`, {
      headers: {
        apikey: key,
        Authorization: `Bearer ${key}`,
        Range: `${from}-${to}`,
        Prefer: "count=exact",
      },
    });
    if (!res.ok) {
      const body = (await res.text()).slice(0, 400);
      throw new Error(`${table} HTTP ${res.status}: ${body}`);
    }
    const page = await res.json();
    if (!Array.isArray(page) || page.length === 0) {
      break;
    }
    rows.push(...page);
    if (page.length < PAGE) {
      break;
    }
    from += PAGE;
  }
  return rows;
}

function insertIgnore(table, columns, rows, pick) {
  if (rows.length === 0) {
    return `-- ${table}: 0 rows\n`;
  }
  const chunks = [];
  for (let i = 0; i < rows.length; i += BATCH) {
    const slice = rows.slice(i, i + BATCH);
    const values = slice
      .map((row) => `(${pick(row).join(", ")})`)
      .join(",\n  ");
    chunks.push(
      `INSERT IGNORE INTO \`${table}\` (${columns.map((c) => `\`${c}\``).join(", ")}) VALUES\n  ${values};`,
    );
  }
  return `-- ${table}: ${rows.length} rows\n${chunks.join("\n")}\n`;
}

const cities = await fetchAll("cities");
const categories = await fetchAll("categories");
const companies = await fetchAll("companies");
const companyCategories = await fetchAll(
  "company_categories",
  "company_id.asc,category_id.asc",
);
const companyMedia = await fetchAll("company_media");
const reviews = await fetchAll("reviews");
const profiles = await fetchAll("profiles");
const posts = await fetchAll("posts");
const publicServices = await fetchAll("public_services");
const emergency = await fetchAll("emergency_contacts");
const systemSettings = await fetchAll("system_settings", "key");

const userIds = new Set();
for (const row of profiles) {
  if (row.id) userIds.add(row.id);
}
for (const row of companies) {
  if (row.owner_id) userIds.add(row.owner_id);
}
for (const row of reviews) {
  if (row.user_id) userIds.add(row.user_id);
}
for (const row of posts) {
  if (row.author_id) userIds.add(row.author_id);
}

const users = [...userIds].map((id) => ({
  id,
  email: `migrated-${id}@invalid.local`,
}));

const out = [];
out.push(`-- Public catalog exported from the live Supabase project via anon REST.
-- Not a GitHub dump (GitHub has schema only). Auth passwords are NOT included.
-- Stub users cannot log in. Apply after 001-009. No DROP / TRUNCATE.
`);

out.push(
  insertIgnore(
    "users",
    ["id", "email", "password_hash", "email_verified_at", "created_at"],
    users,
    (row) => [
      sqlString(row.id),
      sqlString(row.email),
      sqlString(STUB_HASH),
      "NULL",
      nowTs(),
    ],
  ),
);

out.push(
  insertIgnore(
    "profiles",
    ["id", "name", "avatar_url", "created_at", "updated_at"],
    profiles,
    (row) => [
      sqlString(row.id),
      sqlText(row.name),
      sqlText(row.avatar_url),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "cities",
    [
      "id",
      "name",
      "slug",
      "state",
      "lat",
      "lng",
      "hero_title",
      "hero_subtitle",
      "hero_image_url",
      "banner_url",
      "logo_url",
      "video_url",
      "primary_color",
      "seo_title",
      "seo_description",
      "og_image_url",
      "featured_category_ids",
      "is_active",
      "created_at",
      "updated_at",
    ],
    cities,
    (row) => [
      sqlString(row.id),
      sqlString(row.name),
      sqlString(row.slug),
      sqlString(row.state || "MG"),
      sqlNum(row.lat),
      sqlNum(row.lng),
      sqlText(row.hero_title),
      sqlText(row.hero_subtitle),
      sqlText(row.hero_image_url),
      sqlText(row.banner_url),
      sqlText(row.logo_url),
      sqlText(row.video_url),
      sqlText(row.primary_color),
      sqlText(row.seo_title),
      sqlText(row.seo_description),
      sqlText(row.og_image_url),
      row.featured_category_ids == null
        ? sqlJson([])
        : sqlJson(row.featured_category_ids),
      sqlBool(row.is_active, 1),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "categories",
    ["id", "name", "slug", "icon", "description", "sort", "created_at"],
    categories,
    (row) => [
      sqlString(row.id),
      sqlString(row.name),
      sqlString(row.slug),
      sqlText(row.icon),
      sqlText(row.description),
      sqlNum(row.sort) === "NULL" ? "0" : sqlNum(row.sort),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "companies",
    [
      "id",
      "owner_id",
      "slug",
      "name",
      "tagline",
      "description",
      "phone",
      "whatsapp",
      "email",
      "address",
      "zip",
      "city_id",
      "lat",
      "lng",
      "website",
      "instagram",
      "facebook",
      "tiktok",
      "youtube",
      "hours",
      "logo_url",
      "banner_url",
      "video_url",
      "tour_360_url",
      "catalog_url",
      "pricebook_url",
      "portfolio_pdf_url",
      "plan",
      "plan_expires_at",
      "featured",
      "status",
      "is_verified",
      "rating",
      "review_count",
      "views_count",
      "founded_year",
      "years_experience",
      "response_time_minutes",
      "response_rate",
      "services_completed",
      "clients_served",
      "certifications",
      "coverage_cities",
      "quality_scores",
      "reputation_score",
      "badges",
      "price_range",
      "promotions",
      "financing_info",
      "differentials",
      "created_at",
      "updated_at",
    ],
    companies,
    (row) => [
      sqlString(row.id),
      sqlText(row.owner_id),
      sqlString(row.slug),
      sqlString(row.name),
      sqlText(row.tagline),
      sqlText(row.description),
      sqlText(row.phone),
      sqlText(row.whatsapp),
      sqlText(row.email),
      sqlText(row.address),
      sqlText(row.zip),
      sqlText(row.city_id),
      sqlNum(row.lat),
      sqlNum(row.lng),
      sqlText(row.website),
      sqlText(row.instagram),
      sqlText(row.facebook),
      sqlText(row.tiktok),
      sqlText(row.youtube),
      sqlJson(row.hours),
      sqlText(row.logo_url),
      sqlText(row.banner_url),
      sqlText(row.video_url),
      sqlText(row.tour_360_url),
      sqlText(row.catalog_url),
      sqlText(row.pricebook_url),
      sqlText(row.portfolio_pdf_url),
      sqlString(row.plan || "free"),
      sqlTs(row.plan_expires_at),
      sqlBool(row.featured, 0),
      sqlString(row.status || "active"),
      sqlBool(row.is_verified, 0),
      sqlNum(row.rating) === "NULL" ? "0.00" : sqlNum(row.rating),
      sqlNum(row.review_count) === "NULL" ? "0" : sqlNum(row.review_count),
      sqlNum(row.views_count) === "NULL" ? "0" : sqlNum(row.views_count),
      sqlNum(row.founded_year),
      sqlNum(row.years_experience),
      sqlNum(row.response_time_minutes),
      sqlNum(row.response_rate),
      sqlNum(row.services_completed),
      sqlNum(row.clients_served),
      sqlJson(row.certifications),
      sqlJson(row.coverage_cities),
      sqlJson(row.quality_scores),
      sqlNum(row.reputation_score),
      sqlJson(row.badges),
      sqlNum(row.price_range),
      sqlJson(row.promotions),
      sqlJson(row.financing_info),
      sqlJson(row.differentials),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "company_categories",
    ["company_id", "category_id"],
    companyCategories,
    (row) => [sqlString(row.company_id), sqlString(row.category_id)],
  ),
);

out.push(
  insertIgnore(
    "company_media",
    ["id", "company_id", "url", "type", "caption", "sort", "created_at"],
    companyMedia,
    (row) => [
      sqlString(row.id),
      sqlString(row.company_id),
      sqlString(row.url),
      sqlString(row.type || "image"),
      sqlText(row.caption),
      sqlNum(row.sort) === "NULL" ? "0" : sqlNum(row.sort),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "reviews",
    [
      "id",
      "company_id",
      "user_id",
      "rating",
      "comment",
      "author_name",
      "source",
      "review_date",
      "created_at",
    ],
    reviews,
    (row) => [
      sqlString(row.id),
      sqlString(row.company_id),
      sqlText(row.user_id),
      sqlNum(row.rating) === "NULL" ? "0" : sqlNum(row.rating),
      sqlText(row.comment),
      sqlText(row.author_name),
      sqlString(row.source || "app"),
      sqlTs(row.review_date),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "posts",
    [
      "id",
      "type",
      "status",
      "slug",
      "title",
      "excerpt",
      "content",
      "featured_image",
      "gallery",
      "tags",
      "meta_title",
      "meta_description",
      "og_image",
      "author_id",
      "author_name",
      "company_id",
      "city_id",
      "auto_generated",
      "views_count",
      "published_at",
      "scheduled_at",
      "created_at",
      "updated_at",
    ],
    posts,
    (row) => [
      sqlString(row.id),
      sqlString(row.type || "blog"),
      sqlString(row.status || "published"),
      sqlString(row.slug),
      sqlString(row.title),
      sqlText(row.excerpt),
      sqlText(row.content),
      sqlText(row.featured_image),
      sqlJson(row.gallery),
      sqlJson(row.tags),
      sqlText(row.meta_title),
      sqlText(row.meta_description),
      sqlText(row.og_image),
      sqlText(row.author_id),
      sqlText(row.author_name),
      sqlText(row.company_id),
      sqlText(row.city_id),
      sqlBool(row.auto_generated, 0),
      sqlNum(row.views_count) === "NULL" ? "0" : sqlNum(row.views_count),
      sqlTs(row.published_at),
      sqlTs(row.scheduled_at),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "public_services",
    [
      "id",
      "city_id",
      "name",
      "category",
      "subtype",
      "description",
      "address",
      "neighborhood",
      "phone",
      "phone_secondary",
      "whatsapp",
      "email",
      "website",
      "hours",
      "lat",
      "lng",
      "featured",
      "is_24h",
      "active",
      "created_at",
      "updated_at",
    ],
    publicServices,
    (row) => [
      sqlString(row.id),
      sqlString(row.city_id),
      sqlString(row.name),
      sqlString(row.category),
      sqlText(row.subtype),
      sqlText(row.description),
      sqlText(row.address),
      sqlText(row.neighborhood),
      sqlText(row.phone),
      sqlText(row.phone_secondary),
      sqlText(row.whatsapp),
      sqlText(row.email),
      sqlText(row.website),
      sqlText(row.hours),
      sqlNum(row.lat),
      sqlNum(row.lng),
      sqlBool(row.featured, 0),
      sqlBool(row.is_24h, 0),
      sqlBool(row.active, 1),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "emergency_contacts",
    [
      "id",
      "city_id",
      "name",
      "phone",
      "description",
      "icon",
      "sort_order",
      "active",
      "created_at",
      "updated_at",
    ],
    emergency,
    (row) => [
      sqlString(row.id),
      sqlText(row.city_id),
      sqlString(row.name),
      sqlString(row.phone),
      sqlText(row.description),
      sqlText(row.icon),
      sqlNum(row.sort_order) === "NULL" ? "0" : sqlNum(row.sort_order),
      sqlBool(row.active, 1),
      sqlTs(row.created_at) === "NULL" ? nowTs() : sqlTs(row.created_at),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

out.push(
  insertIgnore(
    "system_settings",
    ["key", "value", "is_public", "updated_at"],
    systemSettings,
    (row) => [
      sqlString(row.key),
      sqlJson(row.value),
      sqlBool(row.is_public, 0),
      sqlTs(row.updated_at) === "NULL" ? nowTs() : sqlTs(row.updated_at),
    ],
  ),
);

const dest = path.join("database", "migrations", "010_seed_public.sql");
fs.writeFileSync(dest, out.join("\n"), "utf8");

console.log(
  JSON.stringify(
    {
      file: dest,
      users: users.length,
      profiles: profiles.length,
      cities: cities.length,
      categories: categories.length,
      companies: companies.length,
      company_categories: companyCategories.length,
      company_media: companyMedia.length,
      reviews: reviews.length,
      posts: posts.length,
      public_services: publicServices.length,
      emergency_contacts: emergency.length,
      system_settings: systemSettings.length,
      bytes: fs.statSync(dest).size,
    },
    null,
    2,
  ),
);
