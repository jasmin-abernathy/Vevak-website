# VeVak website

Official lightweight website for **VeVak**, a privacy-first, open-source Android project for on-demand location sharing by SMS.

Production target: `https://vevak.lepotager.org`.

## Current website

The repository contains a deployable static bilingual site:

```text
.
├── index.html          # French home page
├── en/
│   └── index.html      # English home page
├── assets/
│   ├── styles.css      # local design system, no framework
│   ├── site.js         # language preference only
│   └── favicon.svg
├── robots.txt
├── sitemap.xml
├── DEPLOYMENT.md
├── .nojekyll
└── LICENSE
```

There are no external fonts, frameworks, trackers, analytics scripts, advertising SDKs or CDN dependencies.

## Product promise

> A backup location request by SMS, local and under the user's control.

VeVak lets explicitly authorised trusted contacts request the phone's location by SMS. The core feature is designed to work locally on the device without a VeVak account, mandatory cloud server, advertising, tracking or telemetry.

The current free prototype also includes:

- up to five locally configured trusted contacts;
- a separate finite authorisation and revocation state for each contact;
- globally enforced anti-tracking rate limits;
- explicit one-time outgoing location sharing;
- an optional trusted Wi-Fi shortcut;
- temporary discreet notifications that remain locally visible;
- an optional duress/fallback phrase;
- password-encrypted `.vvk` configuration export/import;
- restore behaviour that never silently re-authorises contacts.

VeVak still depends on a working mobile network/SMS service, Android permissions, device background behaviour and location availability. It must not be presented as always available or as a replacement for emergency services.

## Website principles

- French at the root, English under `/en/`;
- system fonts only;
- no cookie banner because the site sets no tracking/advertising cookies;
- a small localStorage language preference is the only client-side persistence;
- responsive and keyboard-friendly layout;
- reduced-motion preference respected;
- product status and limitations visible before future download calls-to-action;
- no claim that research or premium candidates are already shipped.

## Current product direction — August 2026

The Android prototype already passes automated FOSS and Play tests/build/lint. The next priority is real-device validation:

1. real SMS → location → reply, including screen-off/background behaviour;
2. several authorised senders and per-contact revoke/expiry behaviour;
3. robust dual-SIM/eSIM behaviour;
4. encrypted export/import across real devices/document providers;
5. manufacturer/battery diagnostics and accessibility review.

The free safety baseline remains open source. Future paid candidates are limited to optional convenience or genuinely service-backed features, such as several trusted places, advanced response profiles, cross-device conveniences or a future optional encrypted relay. These are roadmap candidates, not shipped promises.

## Deployment

See [`DEPLOYMENT.md`](DEPLOYMENT.md).

For the first publication, the simplest and safest route is to create `vevak.lepotager.org` in o2switch cPanel and upload the static files with cPanel's File Manager. The repository also contains an optional GitHub Actions SSH deployment workflow, but o2switch SSH access is normally protected by an IP allowlist, so hosted GitHub runners may require additional infrastructure before fully automatic SSH deployment is reliable.

## Related repositories

- `jasmin-abernathy/vevak` — Android application, issues and roadmap. FOSS and Play builds are Gradle product flavors in the same codebase.
- `jasmin-abernathy/vevak-docs` — project and technical documentation.
- `jasmin-abernathy/vevak-brand` — brand/design and communication guidance.

## Local preview

Any basic local static server is sufficient, for example:

```bash
python3 -m http.server 8080
```

Then open `http://localhost:8080/`.

## Licence

GNU GPL v3. See `LICENSE`.
