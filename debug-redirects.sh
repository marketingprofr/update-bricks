#!/bin/bash
# ============================================================================
# MEILLEURTEST — Diagnostic des chaînes de redirections
#
# Usage :
#   ./debug-redirects.sh https://meilleurtest.fr/comparatif-imprimante-ecotank/
#   ./debug-redirects.sh https://meilleurtest.fr/comparatif-imprimante-ecotank/ https://meilleurtest.fr/comparatif-aspirateur-robot/
#   ./debug-redirects.sh --batch urls.txt
#
# Avec urls.txt contenant une URL par ligne.
# ============================================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

MAX_HOPS=15

trace_url() {
    local url="$1"
    local hop=0
    local current_url="$url"

    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${CYAN}URL testée : ${NC}${url}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    while [ $hop -lt $MAX_HOPS ]; do
        hop=$((hop + 1))

        # Récupérer les headers
        headers=$(curl -sS -o /dev/null -D - \
            --max-time 10 \
            -A "Mozilla/5.0 (compatible; MeilleurTest-Debug/1.0)" \
            "$current_url" 2>&1)

        if [ $? -ne 0 ]; then
            echo -e "  ${RED}[ERREUR] Impossible de joindre ${current_url}${NC}"
            echo -e "  ${RED}         curl: ${headers}${NC}"
            return 1
        fi

        # Extraire le status code (dernière ligne HTTP/...)
        status=$(echo "$headers" | grep -oP 'HTTP/[0-9.]+ \K[0-9]+' | tail -1)
        status_line=$(echo "$headers" | grep -i '^HTTP/' | tail -1 | tr -d '\r')

        # Extraire Location si présent
        location=$(echo "$headers" | grep -i '^location:' | tail -1 | sed 's/^[Ll]ocation: *//;s/\r$//')

        # Extraire X-Redirect-By si présent (WP ajoute ça)
        redirect_by=$(echo "$headers" | grep -i '^x-redirect-by:' | tail -1 | sed 's/^[Xx]-[Rr]edirect-[Bb]y: *//;s/\r$//')

        # Afficher le hop
        if [[ "$status" =~ ^3 ]]; then
            echo -e "  ${YELLOW}[Hop ${hop}] ${status_line}${NC}"
            echo -e "           ${current_url}"
            echo -e "        → ${location}"
            if [ -n "$redirect_by" ]; then
                echo -e "           ${YELLOW}(X-Redirect-By: ${redirect_by})${NC}"
            fi
            # Résoudre les URLs relatives
            if [[ "$location" == /* ]]; then
                # URL relative : extraire le domaine de l'URL courante
                domain=$(echo "$current_url" | grep -oP 'https?://[^/]+')
                current_url="${domain}${location}"
            else
                current_url="$location"
            fi
        elif [[ "$status" == "200" ]]; then
            echo -e "  ${GREEN}[Hop ${hop}] ${status_line}  ✓ OK${NC}"
            echo -e "           ${current_url}"
            echo ""
            echo -e "  ${GREEN}RÉSULTAT : La page répond correctement après ${hop} hop(s).${NC}"
            return 0
        elif [[ "$status" == "404" ]]; then
            echo -e "  ${RED}[Hop ${hop}] ${status_line}  ✗ 404 NOT FOUND${NC}"
            echo -e "           ${current_url}"
            echo ""
            echo -e "  ${RED}PROBLÈME : 404 sur ${current_url}${NC}"
            echo -e "  ${RED}           C'est ici que la chaîne casse.${NC}"
            return 1
        elif [[ "$status" == "410" ]]; then
            echo -e "  ${RED}[Hop ${hop}] ${status_line}  ✗ 410 GONE${NC}"
            echo -e "           ${current_url}"
            echo ""
            echo -e "  ${RED}PROBLÈME : Le snippet 410 Gone intercepte cette URL.${NC}"
            return 1
        else
            echo -e "  ${RED}[Hop ${hop}] ${status_line}${NC}"
            echo -e "           ${current_url}"
            echo ""
            echo -e "  ${RED}PROBLÈME : Status inattendu ${status} sur ${current_url}${NC}"
            return 1
        fi
    done

    echo ""
    echo -e "  ${RED}PROBLÈME : Boucle de redirection détectée (${MAX_HOPS}+ hops)${NC}"
    return 1
}

test_both_forms() {
    local url="$1"
    local slug=""
    local base_url=""

    # Extraire le domaine
    base_url=$(echo "$url" | grep -oP 'https?://[^/]+')

    # Détecter si c'est une URL plate ou native et tester les deux formes
    if echo "$url" | grep -qP '/comparatif-([^/]+)'; then
        slug=$(echo "$url" | grep -oP '/comparatif-\K([^/]+)')
        echo ""
        echo -e "${CYAN}Slug détecté : ${slug}${NC}"
        echo -e "${CYAN}Test des deux formes d'URL :${NC}"

        echo ""
        echo -e "${CYAN}── Forme plate : /comparatif-${slug}/ ──${NC}"
        trace_url "${base_url}/comparatif-${slug}/"

        echo ""
        echo -e "${CYAN}── Forme native : /comparatif/${slug}/ ──${NC}"
        trace_url "${base_url}/comparatif/${slug}/"

    elif echo "$url" | grep -qP '/comparatif/([^/]+)'; then
        slug=$(echo "$url" | grep -oP '/comparatif/\K([^/]+)')
        echo ""
        echo -e "${CYAN}Slug détecté : ${slug}${NC}"
        echo -e "${CYAN}Test des deux formes d'URL :${NC}"

        echo ""
        echo -e "${CYAN}── Forme native : /comparatif/${slug}/ ──${NC}"
        trace_url "${base_url}/comparatif/${slug}/"

        echo ""
        echo -e "${CYAN}── Forme plate : /comparatif-${slug}/ ──${NC}"
        trace_url "${base_url}/comparatif-${slug}/"
    else
        trace_url "$url"
    fi
}

# ============================================================================
# MAIN
# ============================================================================

if [ $# -eq 0 ]; then
    echo "Usage :"
    echo "  $0 <url> [url2] [url3] ..."
    echo "  $0 --batch fichier.txt"
    exit 1
fi

echo ""
echo "========================================"
echo "  MEILLEURTEST — Debug Redirections"
echo "========================================"

if [ "$1" == "--batch" ] && [ -n "$2" ]; then
    if [ ! -f "$2" ]; then
        echo -e "${RED}Fichier introuvable : $2${NC}"
        exit 1
    fi
    while IFS= read -r line; do
        [[ -z "$line" || "$line" == \#* ]] && continue
        test_both_forms "$line"
    done < "$2"
else
    for url in "$@"; do
        test_both_forms "$url"
    done
fi

echo ""
echo "========================================"
echo "  Diagnostic terminé"
echo "========================================"
echo ""
