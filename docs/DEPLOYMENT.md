# Deployment

Deploys the application to a single server with HTTPS, on free infrastructure.

The stack is the same as local development, minus the local SQL Server and plus
a reverse proxy that manages its own certificate.

---

## Before you start: the one thing that can stop everything

The application reads and writes the federation's SQL Server directly. If they
allow connections only from whitelisted addresses, a new server will be refused
and no amount of configuration will fix it — only the federation can add it.

**Test this the moment the server exists, before configuring anything else:**

```bash
nc -zv wsdev.emiratesequestrian.ae 50441
```

`succeeded` — carry on.
`timed out` — stop. Ask the federation to whitelist the server's IP, or keep
using a tunnel from a machine they already allow.

---

## 1. Create the server

Oracle Cloud "Always Free" is free permanently, unlike the twelve-month trials.

- Image: **Ubuntu 24.04**
- Shape: **VM.Standard.A1.Flex**, 2 OCPU / 12 GB is ample
- Save the SSH private key at creation — it is shown once

If the ARM shape reports no capacity, retry over a day or pick another region.
Alternatives that also work: Hetzner CX22, DigitalOcean, Vultr (has a UAE
region, which is closest to the federation's server).

**Open ports 80 and 443** in the instance's ingress rules. Oracle blocks them by
default and the certificate request will fail without port 80.

---

## 2. Point a domain at it

A hostname is required: PayTabs will not call back to a URL without a valid
certificate, and certificates are not issued for bare IP addresses.

Free option — [duckdns.org](https://duckdns.org): sign in, create a subdomain,
and set it to the server's public IP.

```
uaeerf-anwar.duckdns.org  →  123.45.67.89
```

Verify before continuing, or the certificate request will fail:

```bash
dig +short uaeerf-anwar.duckdns.org
```

---

## 3. Install Docker

```bash
ssh ubuntu@YOUR_SERVER_IP
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
exit          # log back in for the group to apply
```

---

## 4. Get the code

```bash
git clone https://github.com/Anwar-Barakat/uaeerf-portal-assessment.git
cd uaeerf-portal-assessment
```

---

## 5. Write the environment file

Create `.env` on the server **by hand**. Do not copy the development one
wholesale: several values must differ, and secrets should not travel through
shell history or a synced folder.

```bash
cp .env.example .env
nano .env
```

Values that must be set for production:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://uaeerf-anwar.duckdns.org
APP_DOMAIN=uaeerf-anwar.duckdns.org

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/database/sqlite/database.sqlite

PAYTABS_CALLBACK_URL=https://uaeerf-anwar.duckdns.org/api/paytabs/webhook
PAYTABS_RETURN_URL=https://uaeerf-anwar.duckdns.org/api/payment/return
```

Then the federation and PayTabs credentials, retyped from your own records.

`APP_DEBUG=false` matters: with it on, a stack trace containing connection
details can be rendered to anyone who triggers an error.

---

## 6. Bring it up

```bash
mkdir -p database/sqlite && touch database/sqlite/database.sqlite

docker compose -f docker-compose.prod.yml up -d --build

docker compose -f docker-compose.prod.yml exec app php artisan key:generate
docker compose -f docker-compose.prod.yml exec app composer install --no-dev --optimize-autoloader
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml exec app php artisan route:cache
```

The first build takes several minutes: the SQL Server driver is compiled from
source.

### Frontend assets

Build them locally and copy them up, rather than installing Node on the server:

```bash
# on your machine
npm run build
scp -r public/build ubuntu@YOUR_SERVER_IP:~/uaeerf-portal-assessment/public/
```

---

## 7. Point PayTabs at the new domain

In the PayTabs dashboard, set the callback and return URLs to the values in
step 5.

**Skipping this means payments are captured and never fulfilled** — the webhook
would still be delivered to wherever it pointed before.

---

## 8. Verify

```bash
curl -I https://uaeerf-anwar.duckdns.org        # expect 200, valid certificate
docker compose -f docker-compose.prod.yml logs caddy | tail
docker compose -f docker-compose.prod.yml logs queue | tail
```

Then end to end, which is the only proof that counts:

1. Register an account, and confirm the row in `eefsys.WebUserProfile`
2. Submit one competition entry with payment
3. Confirm the row in `eefsys.ClassEntriesWeb` with a matching `Reference`

---

## Redeploying

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan config:cache
docker compose -f docker-compose.prod.yml restart queue
```

Restarting the queue is not optional. `queue:work` holds the application in
memory and keeps running the code it started with — during development that
produced a renewal submitted with empty fields the database row plainly
contained.

---

## Notes on running a live demo

The application writes to the federation's **production** database and takes
real payments. So:

- Share the URL with the people who need it, not publicly
- Take it down when the assessment is over

```bash
docker compose -f docker-compose.prod.yml down
```

---

## Troubleshooting

**Certificate not issued.** The domain must resolve to the server *before*
Caddy starts, and port 80 must be open. Check with `dig +short`, then
`docker compose -f docker-compose.prod.yml restart caddy`.

**Federation database unreachable.** Run the `nc` check from the top of this
document. If it times out, the server's IP is not permitted.

**Payment captured, nothing submitted.** The queue worker is not running, or
the PayTabs URLs still point elsewhere:

```bash
docker compose -f docker-compose.prod.yml logs queue
```

**Build fails on the SQL Server driver.** The Dockerfile detects the
architecture; if you edited it, make sure it is not pinned to one.
