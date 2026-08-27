# VeVak website

Official lightweight website for **VeVak**, a privacy-first, open-source Android project for on-demand location sharing by SMS.

## Current website

The repository now contains a deployable static bilingual site inspired by the editorial / low-footprint approach used for `app.lepotager.org`:

```text
.
├── index.html          # French home page
├── en/
│   └── index.html      # English home page
├── assets/
│   ├── styles.css      # local design system, no framework
│   ├── site.js         # language preference only
│   └── favicon.svg
├── .nojekyll
└── LICENSE
```

There are no external fonts, frameworks, trackers, analytics scripts, advertising SDKs or CDN dependencies.

## Product promise

> A backup location request by SMS, local and under the user's control.

VeVak lets a previously authorised trusted contact request the phone's approximate location by SMS. The core feature is designed to work locally on the device without a VeVak account, mandatory cloud server, advertising, tracking or telemetry.

VeVak still depends on a working mobile network/SMS service, Android permissions, device background behaviour and location availability. It must not be presented as working “without any network”, as always available, or as a replacement for emergency services.

## Website principles

- French at the root, English under `/en/`;
- system fonts only;
- no cookie banner because the site sets no tracking/advertising cookies;
- a small localStorage language preference is the only client-side persistence;
- responsive and keyboard-friendly layout;
- reduced-motion preference respected;
- product status and limitations visible before future download calls-to-action;
- no claim that research features are already implemented.

## Current product direction — August 2026

The Android core remains focused on SMS request → validation → bounded location lookup → SMS response.

Before larger features, the project prioritises:

1. reproducible/installable builds;
2. real SMS tests, including screen-off/background behaviour;
3. robust dual-SIM/eSIM behaviour;
4. guided end-to-end readiness tests;
5. manufacturer/battery diagnostics with actionable guidance;
6. accessibility, security and privacy review.

Later extensions under study include an explicit outgoing SOS to a trusted contact and a local-first device-recovery module inspired by useful ideas from Find Hub/Find Device. The website deliberately presents these as roadmap/research items, not shipped functionality.

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
