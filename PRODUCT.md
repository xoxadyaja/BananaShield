# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

- **Monitoring Personnel** are the primary field operators. They use camera-enabled devices to follow guided capture paths, submit banana plant images and contextual farm information, save screening results as cases, and document follow-up observations, actions, images, and case-status changes.
- **Farm Owner** is the primary reviewer and farm decision-maker. The owner reviews submitted cases and follow-up histories, records review decisions, monitors operational summaries and analytics, maintains farm details, sections, and notification preferences, and can create, update, or delete unused Monitoring Personnel accounts without granting elevated roles or removing historical farm records.
- **System Administrator** maintains authorized users and roles across the system, supported disease categories, versioned advisory content, model registry entries, confidence thresholds, and audit records without altering historical screening results.

## Product Purpose

BananaShield is a centralized farm decision-support system for a selected banana farm in Padada, Davao del Sur. It helps farm personnel perform consistent preliminary visual screening, preserve field observations as traceable cases, follow changes over time, retrieve relevant advisory information, and review system-recorded farm activity.

Success means that personnel can capture useful evidence, report and revisit cases consistently, recognize uncertain results, and make better-informed farm-management decisions without presenting software output as a confirmed diagnosis.

## Positioning

BananaShield combines guided banana-plant image screening with the full farm-case lifecycle: contextual reporting, private image records, follow-ups, owner review, advisory support, operational monitoring, and analytics. Both Leaf Screening and Whole-Plant Screening are guidance paths into one shared four-class model contract rather than separate disease models.

## Operating Context

- Used through compatible desktop and mobile web browsers on internet-connected devices, including camera-enabled smartphones used during farm observation.
- Designed around a selected banana farm in Padada, Davao del Sur and the locally cultivated Cardava, Binangay, and Tundan varieties.
- Monitoring Personnel perform field screening and reporting; the Farm Owner reviews the resulting operational record; the System Administrator maintains system configuration.
- Uploaded images, observation details, screening outputs, follow-ups, case status, owner review, and audit events form the durable case history.
- The application consists of a Laravel web application, a FastAPI AI-service contract, MySQL relational storage, and private file storage for case images.

## Capabilities and Constraints

- Authenticate three user roles and enforce distinct role-based workspaces and permissions, including owner-created accounts fixed to the Monitoring Personnel role.
- Guide Monitoring Personnel through Leaf Screening or Whole-Plant Screening, including required image views and capture instructions.
- Accept JPEG, PNG, or WebP images up to 5 MB and record relevant case context.
- Support four output categories: Healthy Banana, Black Sigatoka, Fusarium Wilt, and Banana Bunchy Top Disease.
- Display a confidence score, image-quality context, diagnostic disclaimer, and relevant advisory information.
- Convert low-confidence, poor-quality, mismatched-view, unavailable-service, or unsupported outcomes into an inconclusive or failed result rather than a completed disease claim.
- Save cases, private images, predictions, follow-up observations and images, actions, case statuses, owner reviews, advisories, model records, and audit logs.
- Give the Farm Owner monitoring summaries and analytics derived only from BananaShield records. These are not official incidence, prevalence, outbreak, or epidemiological-surveillance statistics.
- Preserve historical predictions and advisory versions when administrators change active categories, advisory content, model registry entries, or thresholds.
- Operate in English and remain usable on responsive layouts down to a 360-pixel viewport.
- Require network, database, storage, and AI-service availability for the corresponding workflows; there is no offline classification or synchronization.
- The current system is a capstone/research prototype. `AI_MODE=mock` exercises the workflow but does not perform learned classification or establish accuracy. A trained and independently evaluated EfficientNet-B0 model, documented metrics, and a validated confidence threshold are required before model-backed field use.
- BananaShield provides preliminary visual decision support only. It does not perform laboratory testing, detect pre-symptomatic disease, determine disease severity or pathogen race, authorize treatment, or replace qualified agricultural assessment.

## Brand Commitments

- Preserve the **BananaShield** product name and the existing banana-and-shield logo at `web-application/public/images/bananashield-logo.svg`.
- Preserve the product's Padada and Davao del Sur agricultural context and its focus on Cardava, Binangay, and Tundan bananas.
- Preserve the English interface unless a future localization decision explicitly changes that commitment.
- Keep safety language factual and restrained: classification is preliminary, confidence is not confirmation, and uncertain or serious cases should be referred to qualified agricultural personnel or an appropriate laboratory.

## Evidence on Hand

- The current Laravel implementation is under `web-application/`, with role-based workflows, case monitoring, advisory, analytics, farm settings, and administration.
- The FastAPI service contract and model integration boundary are under `ai-service/`.
- The revised research manuscript is at `D:\Capstone\Document\bananashield rev3.docx`.
- Product and implementation mapping is documented in `README.md` and `documentation/CONCEPTUAL_FRAMEWORK.md`.
- Existing brand assets are `web-application/public/images/bananashield-logo.svg` and `web-application/public/images/banana-field.svg`.
- The repository includes seeded demonstration accounts and automated workflow tests. It does not contain a trained model file, validated accuracy claim, field-deployment evidence, testimonial, regulatory approval, or formal epidemiological dataset. Future work must not fabricate these forms of proof.

## Product Principles

1. **Support judgment; never impersonate diagnosis.** Every result must communicate its uncertainty and the limits of image-based screening.
2. **Treat screening as the beginning of a case history.** Reporting, follow-up evidence, review, and status changes matter as much as the initial classification.
3. **Keep responsibilities unmistakable.** Field submission, owner review, and system maintenance remain separate role-specific workflows.
4. **Prefer traceable farm evidence over unsupported claims.** Preserve source images, context, model/version information, and historical records while labeling analytics accurately.
5. **Fail safely.** Invalid images, unavailable services, unsupported outputs, and low confidence must never become apparently valid disease records.

## Accessibility & Inclusion

- Use understandable labels, capture guidance, result explanations, and recovery messages for users with varied technical experience.
- Maintain keyboard-operable controls, readable status communication, mobile touch targets, and responsive behavior for field and office devices.
- Do not rely on color alone to communicate result, status, warning, or error meaning.
