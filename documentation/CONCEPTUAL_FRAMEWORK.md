# BananaShield Conceptual Framework

This document is the implementation reference for the August 2026 BananaShield Input–Process–Output conceptual framework. BananaShield is a farm-level decision-support system for preliminary visual screening, case recording, follow-up, advisory support, and operational monitoring.

## Input

### Farm Owner

- View the farm dashboard and summary overview, including crop status, case summaries, and recent activity.
- Review recorded cases, submitted cases, preliminary classifications, and follow-up updates.
- Access disease-specific advisory information, management reminders, and preventive measures.
- View farm analytics and reports derived from system-recorded farm cases.
- Create and update Monitoring Personnel accounts, and delete unused accounts, without permission to assign Farm Owner or System Administrator access or remove historical farm records.
- Manage the farm profile, farm sections or areas, and notification preferences.

### Monitoring Personnel

- Capture or upload a banana plant image.
- Select either Leaf Screening or Whole-Plant Screening as guided capture paths. These paths change capture guidance and required views; both use the same shared model integration.
- Record available contextual farm-case information, including banana variety, farm section, observation date, plant age, and visible symptoms.
- Add follow-up observations, actions, images, and case-status updates.

### System Administrator

- Manage authorized role-based user accounts.
- Maintain supported disease information.
- Maintain versioned advisory content.
- Register the shared EfficientNet-B0 integration or a trained and validated EfficientNet-B0 model.
- Maintain model version, confidence threshold, and other operational configuration.

## Process

1. **User authentication and role-based access** — verify credentials and grant access according to the assigned role.
2. **Guided image screening and validation** — accept Leaf Screening or Whole-Plant Screening, then validate file type, size, readability, dimensions, and required plant view.
3. **Image preprocessing** — prepare an accepted image for EfficientNet-B0 inference. The FastAPI model service converts the image to RGB, resizes it to 224 × 224 pixels, and packages it as the float32 batch expected by the configured model when model mode is active.
4. **EfficientNet-B0 image classification** — process accepted images from both capture paths through the same four-class integration.
5. **Confidence-score generation** — return relative model confidence and compare it with the active configured threshold. Low-confidence output becomes inconclusive.
6. **Advisory retrieval and case recording** — match the preliminary output to relevant advisory information and atomically save the farm case, private image record, model reference, prediction, and confidence score.
7. **Follow-up, disease monitoring, and analytics** — record follow-ups and current case statuses, then summarize system-recorded cases for farm-level operational decisions.

## Output

1. **Preliminary disease classification** — Healthy Banana, Black Sigatoka, Fusarium Wilt, Banana Bunchy Top Disease, or an inconclusive result when safeguards require it.
2. **Confidence score** — relative confidence for the preliminary class, displayed with the active inconclusive threshold and an explicit limitation statement.
3. **Relevant advisory information** — disease-specific visible signs, prevention, containment reminders, general guidance, and consultation guidance.
4. **Recorded farm case or report** — private case record containing the submitted image, preliminary classification, confidence score, model reference, and contextual farm information.
5. **Follow-up records** — recorded observations, actions, optional images, and current case status.
6. **Disease-monitoring summaries** — system-recorded case and disease-class summaries within the selected farm.
7. **Analytics based on system-recorded farm cases** — charts and operational insights derived only from records submitted to BananaShield.

## Required interpretation safeguards

- BananaShield produces preliminary visual-screening outputs, not confirmed diagnoses.
- Confidence is relative model certainty; it is not disease probability, severity, or proof.
- Capture paths provide image guidance only and do not select different disease models.
- Mock mode demonstrates workflow behavior and is not learned image classification.
- A model may be described as trained or validated only when its file, evaluation evidence, version, and threshold have been reviewed and registered.
- Monitoring summaries and analytics describe only system-recorded submissions and are not official incidence, prevalence, outbreak, or epidemiological-surveillance statistics.
- Serious, worsening, unsupported, or uncertain cases require qualified agricultural assessment or laboratory confirmation.

## Implementation mapping

| Framework element | BananaShield implementation |
| --- | --- |
| Authentication and role access | Laravel authentication, active-account checks, and role middleware |
| Guided capture and validation | Leaf/Whole-Plant wizard, required-view rules, image MIME/size/dimension checks |
| Preprocessing and classification | Shared FastAPI EfficientNet-B0 contract; transparent mock mode by default |
| Confidence and safeguards | Active model registry threshold, inconclusive handling, quality flags, disclaimers |
| Advisory retrieval | Versioned disease and advisory records with a built-in safe fallback |
| Farm case/report | Transactional case, private image, prediction, and audit records |
| Farm profile input | Farm Owner profile, sections/areas, and notification-preference module |
| Follow-up and monitoring | Case history, follow-up observations/images/actions, owner review, current status |
| Analytics output | Six-month activity, capture mix, class distribution, case-status graphs, and metric summaries |
