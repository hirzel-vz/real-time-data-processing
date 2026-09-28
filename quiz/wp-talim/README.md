# wp-talim — Local WordPress + H5P Generation Stack

Dockerized trial environment: WordPress with the H5P plugin, plus the
H5P generation service (document in → `.h5p` out), all in one compose stack.

## Services

| Service | Port | Purpose |
|---|---|---|
| `wordpress` | http://localhost:8080 | WordPress with the h5p-generator plugin pre-installed |
| `db` | — | MariaDB 11 |
| `h5p-service` | http://localhost:8000 | FastAPI generation service (extract → Mistral API → validate → convert) |

The WordPress container reaches the service at `http://h5p-service:8000`
(Docker DNS), configured automatically via `WORDPRESS_CONFIG_EXTRA`.

## Setup

```bash
cp .env.example .env        # then edit: set MISTRAL_API_KEY and DB passwords
docker compose up -d --build
```

Open http://localhost:8080 and complete the WordPress installer once.
Then in wp-admin:

1. **Plugins → Add New → Search "H5P"** → install and activate "H5P"
   (the official plugin — the generator depends on it for uploads)
2. **Plugins** → activate "H5P Content Generator" (pre-installed by the volume mount)
3. **Tools → H5P Generator** → upload a document, generate, review, publish

## Usage flow

1. **Tools → H5P Generator** → choose PDF/DOCX/TXT/MD, quiz or scenario, count
2. Wait ~30-90 s (extraction + generation + conversion)
3. Result screen: **Download .h5p** and **Open JSON** (review the questions!)
4. **H5P Content → Add New → Upload** → select the downloaded `.h5p` → publish

Generated packages and their review JSON are also saved locally in
`./generated/<result_id>/`.

## Files

- `docker-compose.yml` — the stack
- `h5p-service/` — generation service (FastAPI + the json2h5p converters)
- `wordpress/wp-content/` — mounted into the WordPress container
  (`plugins/h5p-generator/` — the admin-UI plugin)
- `.env.example` — copy to `.env`; contains `MISTRAL_API_KEY` (never commit `.env`)

## Notes

- First H5P upload on a fresh WordPress: the H5P plugin will offer to install
  libraries from the H5P Hub — allow it (Question Set, Branching Scenario,
  MultiChoice, BranchingQuestion, AdvancedText).
- The service has no authentication — localhost/trial use only. Add auth
  before exposing it beyond your machine.
- MariaDB data persists in the `db_data` volume; `docker compose down -v`
  resets everything.
- Windows: run Docker Desktop; the stack needs ~2 GB RAM.
