# VeVak website

Official website repository for **VeVak**, a privacy-first, open-source Android project for on-demand location sharing by SMS.

## Product promise

> A backup location request by SMS, local and under the user's control.

VeVak lets a previously authorised trusted contact request the phone's approximate location by SMS. The core feature is designed to work locally on the device without a VeVak account, mandatory cloud server, advertising, tracking or telemetry.

VeVak still depends on a working mobile network/SMS service, Android permissions, device background behaviour and location availability. It must not be presented as working “without any network”, as always available, or as a replacement for emergency services.

## Website goals

The public website should explain, in plain language:

- what VeVak does and does not do;
- how an authorised SMS request works;
- why the project is local-first and open source;
- privacy and consent boundaries;
- current prototype / testing status;
- FOSS-first distribution and the Android variants;
- accessibility, ecodesign and real-device testing;
- how to contribute, test or partner with the project.

## Communication principles

The tone should stay calm, precise, human and non-alarmist. Avoid fear-based marketing or surveillance framing.

Preferred claims:

- local-first;
- one explicitly authorised contact in the current core;
- location requested only when needed;
- no mandatory VeVak server for the core feature;
- no advertising or tracking;
- open-source development;
- limitations documented before activation.

Avoid claims such as “always locatable”, “works without a network”, “guaranteed in an emergency” or “replaces emergency services”.

## Current product direction — August 2026

The Android core remains focused on SMS request → validation → bounded location lookup → SMS response.

Before larger features, the project prioritises:

1. reproducible/installable builds;
2. real SMS tests, including screen-off/background behaviour;
3. robust dual-SIM/eSIM behaviour;
4. guided end-to-end readiness tests;
5. manufacturer/battery diagnostics with actionable guidance;
6. accessibility, security and privacy review.

Later extensions under study include an explicit outgoing SOS to a trusted contact and a local-first device-recovery module inspired by useful ideas from Find Hub/Find Device (for example ringing, battery/state information, lock-screen message and last known location). These extensions must remain consent-based, privacy-preserving, resource-bounded and must not require a mandatory central VeVak server.

## Related repositories

- `jasmin-abernathy/vevak` — Android application and roadmap. The FOSS and Play builds live together as Gradle product flavors so they share the same core and tests.
- `jasmin-abernathy/vevak-docs` — project and technical documentation.
- `jasmin-abernathy/vevak-brand` — brand/design resources and communication rules.

No separate FOSS/Play repositories are currently needed. A separate integration repository would only be created later if a concrete use case genuinely requires a distinct codebase.

## Licence

See `LICENSE`.
