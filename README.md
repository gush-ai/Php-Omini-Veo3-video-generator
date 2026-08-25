# GVid — PHP Video Generation Gateway

Production-oriented PHP starter for exposing Google Veo video generation through clean `/video/...` routes.

## Current provider

Google Gemini API / Veo 3.1:
- `veo-3.1-generate-preview`
- asynchronous long-running generation
- text-to-video
- optional reference image support
- 16:9 / 9:16
- 720p / 1080p / 4k where supported by the selected model/configuration

## Requirements

- PHP 8.1+
- cURL extension
- JSON extension
- writable `storage/` directory
- Apache with `mod_rewrite` for the included clean routes
- Google Gemini API key with paid Gemini API access

## Install

1. Upload the project.
2. Copy `.env.example` to `.env`.
3. Set `GEMINI_API_KEY`.
4. Set a long random `GV_AUTH_TOKEN`.
5. Make `storage/` writable by PHP.
6. Point the web root at `public/`, or use the included Apache rewrite configuration.
7. Test `GET /health`.

## Routes

POST `/video/generate`
GET  `/video/status?job_id=...`
GET  `/video/result?job_id=...`
POST `/video/cancel` (local job cancellation marker; it does not cancel a provider operation)
GET  `/health`

Authentication:
`Authorization: Bearer YOUR_GV_AUTH_TOKEN`

## Generate example

```bash
curl -X POST https://example.com/video/generate \
  -H "Authorization: Bearer YOUR_GV_AUTH_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "prompt":"A cinematic 8-second luxury advert for a smart door lock in Lagos, realistic commercial cinematography, premium lighting.",
    "model":"veo-3.1-generate-preview",
    "aspect_ratio":"9:16",
    "resolution":"720p"
  }'
```

The response contains a local `job_id` and Google's long-running operation name. Poll `/video/status`.

## Reference image

For a small image, send a base64 data URL:

```json
{
  "prompt":"Create a premium product advert. Keep the product design consistent with the reference image.",
  "image":{
    "data":"data:image/jpeg;base64,..."
  }
}
```

The backend converts the data URL into the Gemini API's inline image object.

## Important

Do not put the Google API key in frontend JavaScript. Keep it only in `.env` on the server.

The included provider adapter intentionally isolates Google-specific code so another video provider can be added later without changing the public GVid API.
