# CiviCRM Extension: Loctype FlexListener (nl.onvergetelijk.loctype)

Deze extensie biedt geavanceerde controle over welk e-mailadres van een contact wordt gebruikt bij het verzenden van **Geplande Herinneringen** (Scheduled Reminders). In plaats van altijd het standaard 'Primary' adres te gebruiken, kun je per herinnering forceren dat er naar een specifiek locatietype (bijv. 'Gave', 'Thuis', 'Werk') wordt gemaild.

## 🚀 Functionaliteit

De module voegt een instellingen-icoon (wrench) toe aan het Scheduled Reminder scherm. Hiermee kun je per herinnering een **Locatietype** en een **Selectiemethode** kiezen:

| Methode | Beschrijving |
| :--- | :--- |
| **Automatic** | Gebruikt de standaard CiviCRM logica (Primary adres). |
| **Location Only** | Verstuurt de mail **alleen** als het gekozen type bestaat. Zo niet, dan wordt de verzending afgebroken. |
| **Location Prefer** | Gebruikt het gekozen type indien aanwezig, anders wordt er teruggevallen op het Primary adres. |
| **Location Exclude** | Blokkeert de verzending als het systeem van plan is naar dit specifieke type te mailen. |

---

## 🛠 Installatie & Gebruik

1. Installeer de extensie via de CiviCRM extensiebeheerder of via `cv`:
   `cv en nl.onvergetelijk.loctype`
2. Ga naar **Beheer > Communicatie > Geplande Herinneringen**.
3. Klik op het wrench-icoon naast de instellingen om de gewenste e-mail-locatie te configureren.

---

## 🧪 Ontwikkeling & Testing

De module bevat een uitgebreide suite aan Unit Tests om de verzendlogica te borgen.

### Tests draaien op de server
Gebruik het meegeleverde script in de root van de extensie:
```bash
./run-tests.sh
