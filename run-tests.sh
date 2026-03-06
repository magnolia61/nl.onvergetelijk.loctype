#!/bin/bash

# ==============================================================================
# LOCTYPE TEST RUNNER (CLEAN OUTPUT)
# Voert tests uit en filtert PHP ruis uit de weergave.
# ==============================================================================

echo "########################################################################"
echo "### LOCTYPE [TEST] Starten Unit Tests via API..."
echo "########################################################################"

# Voer de API call uit. We sturen de output door een filter (grep) 
# om Notices en Deprecations te verbergen.
# -v betekent "inverteer match" (laat alles zien BEHALVE deze woorden)

RESULT=$(cv api job.execute name=phpunit test=tests/phpunit/Civi/Loctype/FlexListenerTest.php 2>&1 | grep -vEi "Notice|Deprecated|Strict Standards")

# Check of het resultaat succesvol is (is_error: 0)
if [[ $RESULT == *"is_error\": 0"* ]]; then
    echo "SUCCESS: Test-run succesvol."
    echo "------------------------------------------------------------------------"
    
    # Probeer de JSON mooi weer te geven (als het valide JSON is)
    echo "$RESULT" | python3 -m json.tool 2>/dev/null || echo "$RESULT"
else
    echo "ERROR: Er is een fout opgetreden of de test is mislukt."
    echo "------------------------------------------------------------------------"
    echo "$RESULT"
    exit 1
fi

echo "########################################################################"
echo "### LOCTYPE [TEST] Klaar."
echo "########################################################################"
