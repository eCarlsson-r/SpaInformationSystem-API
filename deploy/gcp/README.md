# Google Cloud Demo Deployment

This is a containerized demo path for Cloud Run. It does not deploy automatically. Use separate Cloud Run services for the API, SpaBooking, and SpaCashier. Keeping both frontends on Cloud Run preserves their dynamic/authenticated routes.

## API

Create the Artifact Registry Docker repository once before the first build. `REPOSITORY` is the registry repository resource, and it must already exist in the same project and region used by the image URL:

```sh
gcloud artifacts repositories create spa-api \
  --repository-format=docker \
  --location=asia-southeast2 \
  --project=spasystem
```

Then build and publish from this directory. The image URL has three path parts after the registry host: project, repository, and image name. In particular, include `/api` after the `spa-api` repository:

```sh
gcloud builds submit \
  --project=spasystem \
  --tag=asia-southeast2-docker.pkg.dev/spasystem/spa-api/api:TAG
```

Use a unique value for `TAG` for each build. If the repository already exists, skip the create command; if it exists in another region, use that region consistently in both commands and the image host.

Create a Cloud Run service with port 8080, a dedicated service account, Cloud SQL attachment, and these non-secret settings:

```text
APP_ENV=production
APP_DEBUG=false
LOG_CHANNEL=stderr
QUEUE_CONNECTION=database
DB_CONNECTION=mysql
DB_SOCKET=/cloudsql/PROJECT:REGION:INSTANCE
DB_DATABASE=DATABASE_NAME
DB_USERNAME=DATABASE_USER
POS_URL=https://CASHIER_HOST
STORE_URL=https://BOOKING_HOST
AI_PROVIDER=gemini
```

Provide `APP_KEY`, `DB_PASSWORD`, and `GEMINI_API_KEY` through Secret Manager. For Xendit sandbox payment tests, also provide `XENDIT_SECRET_KEY` and `XENDIT_WEBHOOK_TOKEN`; configure the success and cancel URLs to the booking site's `/checkout` page. Grant the Cloud Run service account Cloud SQL Client and Secret Manager Secret Accessor roles. Keep the webhook endpoint public, but never expose its callback token in frontend code.

Run migrations as a one-off Cloud Run Job using the same image, service account, Cloud SQL attachment, and database settings:

```sh
gcloud run jobs create spa-api-migrate \
  --image=REGION-docker.pkg.dev/PROJECT/REPOSITORY/spa-api:TAG \
  --region=REGION \
  --set-cloudsql-instances=PROJECT:REGION:INSTANCE \
  --command=php --args=artisan,migrate,--force
gcloud run jobs execute spa-api-migrate --region=REGION --wait
```

Configure the job's database password as a Secret Manager environment variable before execution. Do not run migrations from each web instance at startup.

## Queue Worker

The API dispatches conflict-evaluation and sentiment-analysis jobs. Set `QUEUE_CONNECTION=database` on the API, and create a Cloud Run Job from the same image with the same Cloud SQL attachment and database settings. Configure its container command as:

```text
php artisan queue:work database --queue=conflict-evaluation,sentiment-analysis,default --stop-when-empty --tries=1 --sleep=3 --timeout=90
```

Invoke the job from Cloud Scheduler at a short interval using an authenticated Cloud Run Jobs `:run` request. Set the job to one task with parallelism one, and ensure the queue table migration has run. The worker exits when the queue is empty, so scheduled executions do not require an HTTP listener or an always-on service. Monitor failed jobs and queue age before the demo; use an explicit retry/reconciliation policy for production.

## SpaBooking

`APP_URL` is a public build-time value used to create the API base URL. Build and publish the Nuxt Node server image from the SpaBooking directory:

```sh
docker build --build-arg APP_URL=https://API_HOST \
  -t REGION-docker.pkg.dev/PROJECT/REPOSITORY/spa-booking:TAG .
docker push REGION-docker.pkg.dev/PROJECT/REPOSITORY/spa-booking:TAG
```

Deploy the image to Cloud Run on port 8080, then set `STORE_URL` on the API to the service origin. `APP_URL` contains only the public API origin; Nuxt appends `/api`.

## SpaCashier

Build the standalone Next.js image with the public API URL and Reverb connection settings as build arguments. `NEXT_PUBLIC_*` values are embedded in the browser bundle, so provide them at image build time. The Reverb key and host must match the API's Reverb configuration; these values are public client configuration, not secrets:

```sh
docker build \
  --build-arg NEXT_PUBLIC_API_URL=https://API_HOST/api \
  --build-arg NEXT_PUBLIC_REVERB_APP_KEY=REVERB_APP_KEY \
  --build-arg NEXT_PUBLIC_REVERB_HOST=REVERB_HOST \
  --build-arg NEXT_PUBLIC_REVERB_PORT=443 \
  --build-arg NEXT_PUBLIC_REVERB_SCHEME=https \
  -t REGION-docker.pkg.dev/PROJECT/REPOSITORY/spa-cashier:TAG .
docker push REGION-docker.pkg.dev/PROJECT/REPOSITORY/spa-cashier:TAG
```

Deploy that image to Cloud Run on port 8080 and set `POS_URL` on the API to the service origin. The API uses Sanctum bearer tokens; configure CORS origins to the exact booking and cashier origins.

## Demo Gate

Use a non-production Cloud SQL database and Xendit sandbox credentials. Verify booking hold creation, callback-token and amount checks, webhook replay idempotency, sale/session creation, and staff start/finish actions before showing the demo. Never use a frontend success redirect as payment confirmation; the API webhook is authoritative.