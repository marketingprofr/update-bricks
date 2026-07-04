<?php
/**
 * Tableau de comparaison des produits - Style TechGearLab v2
 * VERSION « TRANSITION »
 *
 * Utilise le produit 1 comme référence pour les critères et specs à afficher.
 *
 * MIGRATION : comme les autres templates intermédiaires, ce bloc tire
 * désormais la sélection de produits de la source canonique du guide
 * (sélection calculée en amont à partir des taxonomies post-type-produit +
 * post-type-attribut, stockée dans les ACF), avec la même cascade que les
 * templates voisins :
 *   1) top_avis_ids         (sélection taxonomique du guide — source utilisée désormais)
 *   2) produits_comparatif  (repli : ancien override éditorial du comparatif)
 *   3) mltv5_best_products  (repli : champ relation ACF)          [AJOUT]
 *   4) mlt_get_meilleur_produit()  (repli legacy par catégorie)   [AJOUT]
 * Les IDs sont ensuite validés (posts existants) avant rendu.
 *
 * Le reste (collecte des données, rendu HTML, offres, toggle, classes CSS)
 * est INCHANGÉ : aucun impact visuel. Les 3 helpers internes sont juste
 * protégés par function_exists pour éviter tout « Cannot redeclare » si le
 * bloc est présent plusieurs fois sur la page.
 *
 * ORDRE DES LIGNES:
 * 1. N° classement
 * 2. Image
 * 3. Nom produit (lien affilié)
 * 4. Label produit
 * 5. Prix indicatif
 * 6. Score global (jauge)
 * 7. Score (étoiles)
 * 8. Résumé produit
 * 9. Scores critères (jauges)
 * 10. Caractéristiques techniques
 */

// ============================================
// CONFIGURATION
// ============================================
$debug_mode = false;
$max_products = 5;
$max_criteria = 8;
$max_specs = 10;

$log_table = function($message, $data = null) use ($debug_mode) {
    if (!$debug_mode) return;
    if ($data !== null) {
        echo "<script>console.log('[COMPARISON] " . addslashes($message) . "', " . wp_json_encode($data) . ");</script>\n";
    } else {
        echo "<script>console.log('[COMPARISON] " . addslashes($message) . "');</script>\n";
    }
};

try {
    global $post;
    if (!is_object($post) || empty($post->ID)) {
        throw new Exception("Pas de post valide");
    }

    $current_post_id = intval($post->ID);

    // ========================================
    // PHASE 1 : RÉCUPÉRATION DES PRODUITS
    // ========================================

    if (!function_exists('get_all_template_variables')) {
        throw new Exception("Fonction get_all_template_variables introuvable");
    }

    $template_vars = get_all_template_variables($current_post_id);
    $product_ids = array();

    // 1) top_avis_ids : sélection taxonomique du guide (source utilisée désormais)
    if (!empty($template_vars['top_avis_ids'])) {
        $product_ids = array_map('intval', $template_vars['top_avis_ids']);
    }

    // 2) produits_comparatif : repli (ancien override éditorial du comparatif)
    if (empty($product_ids) && !empty($template_vars['produits_comparatif'])) {
        foreach ($template_vars['produits_comparatif'] as $item) {
            if (is_array($item) && isset($item['avis_id'])) {
                $pid = is_object($item['avis_id']) ? $item['avis_id']->ID : intval($item['avis_id']);
                if ($pid > 0) $product_ids[] = $pid;
            } elseif (is_numeric($item)) {
                $product_ids[] = intval($item);
            }
        }
    }

    // 3) mltv5_best_products : repli sur le champ relation ACF
    if (empty($product_ids)) {
        $rel = get_field('mltv5_best_products', $current_post_id);
        if (is_array($rel)) {
            foreach ($rel as $r) {
                $product_ids[] = is_object($r) ? $r->ID : intval($r);
            }
        }
    }

    // 4) repli LEGACY : ancien calcul par catégorie (le temps de la transition)
    if (empty($product_ids) && function_exists('mlt_get_meilleur_produit')) {
        for ($position = 1; $position <= $max_products; $position++) {
            $legacy_id = mlt_get_meilleur_produit($position, null, 'id');
            if (empty($legacy_id)) break;
            $product_ids[] = intval($legacy_id);
        }
    }

    // Nettoyage : entiers uniques, limités à $max_products, posts existants
    $product_ids = array_values(array_unique(array_filter(array_map('intval', $product_ids))));
    $product_ids = array_slice($product_ids, 0, $max_products);
    $product_ids = array_values(array_filter($product_ids, function ($pid) { return (bool) get_post($pid); }));

    if (count($product_ids) < 2) {
        return;
    }

    // ========================================
    // PHASE 2 : COLLECTE DES DONNÉES
    // ========================================

    $products_data = array();
    $criteria_counter = array();  // Compteur de fréquence des critères
    $specs_counter = array();     // Compteur de fréquence des specs

    foreach ($product_ids as $index => $pid) {
        $score_recent = floatval(get_field('mltv5_score_recent', $pid) ?: 0);

        $product = array(
            'id' => $pid,
            'rank' => $index + 1,
            'title' => get_the_title($pid),
            'test_url' => get_permalink($pid),
            'image' => get_the_post_thumbnail_url($pid, 'medium'),
            'score_recent' => $score_recent,
            'prix_indicatif' => get_field('mltv5_prix_indicatif', $pid),
            'resume' => get_field('mltv5_resume_produit', $pid),
            'criteria' => array(),
            'specs' => array(),
            // Données pour les offres
            'asin_amazon' => get_field('mltv5_asin_amazon', $pid),
            'liens' => array(
                1 => array(
                    'url' => get_field('mltv5_lien_du_produit_1', $pid),
                    'texte' => get_field('mltv5_texte_du_bouton_1', $pid)
                ),
                2 => array(
                    'url' => get_field('mltv5_lien_du_produit_2', $pid),
                    'texte' => get_field('mltv5_texte_du_bouton_2', $pid)
                ),
                3 => array(
                    'url' => get_field('mltv5_lien_du_produit_3', $pid),
                    'texte' => get_field('mltv5_texte_du_bouton_3', $pid)
                )
            ),
            // Points positifs/négatifs
            'points_positifs' => array(),
            'points_negatifs' => array()
        );

        // Label produit
        if (function_exists('get_default_product_label')) {
            $product['label'] = get_default_product_label($pid, $score_recent);
        } else {
            $product['label'] = '';
        }

        // Lien affilié
        if (function_exists('mlt_get_best_affiliate_link')) {
            $product['affiliate_url'] = mlt_get_best_affiliate_link($pid);
        } else {
            $product['affiliate_url'] = $product['test_url'];
        }

        // Critères - collecter et compter
        $criteria_seen_this_product = array();
        if (have_rows('mltv5_scores_des_criteres', $pid)) {
            while (have_rows('mltv5_scores_des_criteres', $pid)) {
                the_row();
                $label = trim((string) get_sub_field('mltv5_nom_du_critere'));
                $score = trim((string) get_sub_field('mltv5_score_du_critere'));

                if ($label !== '' && $score !== '' && is_numeric($score)) {
                    $product['criteria'][$label] = floatval($score);

                    // Compter une seule fois par produit
                    if (!in_array($label, $criteria_seen_this_product)) {
                        if (!isset($criteria_counter[$label])) {
                            $criteria_counter[$label] = 0;
                        }
                        $criteria_counter[$label]++;
                        $criteria_seen_this_product[] = $label;
                    }
                }
            }
        }

        // Specs techniques - collecter et compter
        $specs_seen_this_product = array();
        if (have_rows('mltv5_caracteristiques_du_produit', $pid)) {
            while (have_rows('mltv5_caracteristiques_du_produit', $pid)) {
                the_row();
                $spec_name = trim((string) get_sub_field('mltv5_caracteristique_produit'));
                $spec_value = trim((string) get_sub_field('mltv5_valeur_caracteristique_produit'));

                if ($spec_name !== '' && $spec_value !== '') {
                    $product['specs'][$spec_name] = $spec_value;

                    // Compter une seule fois par produit
                    if (!in_array($spec_name, $specs_seen_this_product)) {
                        if (!isset($specs_counter[$spec_name])) {
                            $specs_counter[$spec_name] = 0;
                        }
                        $specs_counter[$spec_name]++;
                        $specs_seen_this_product[] = $spec_name;
                    }
                }
            }
        }

        // Points positifs
        $points_positifs_raw = get_field('mltv5_points_positifs_produit', $pid);
        if (is_array($points_positifs_raw)) {
            foreach ($points_positifs_raw as $row) {
                if (is_array($row) && isset($row['mltv5_point_positif'])) {
                    $point = trim($row['mltv5_point_positif']);
                    if (!empty($point)) {
                        $product['points_positifs'][] = $point;
                    }
                }
            }
        }

        // Points négatifs
        $points_negatifs_raw = get_field('mltv5_points_negatifs_produit', $pid);
        if (is_array($points_negatifs_raw)) {
            foreach ($points_negatifs_raw as $row) {
                if (is_array($row) && isset($row['mltv5_point_negatif'])) {
                    $point = trim($row['mltv5_point_negatif']);
                    if (!empty($point)) {
                        $product['points_negatifs'][] = $point;
                    }
                }
            }
        }

        $products_data[] = $product;
    }

    // Filtrer les critères partagés par au moins 3 produits
    $reference_criteria = array();
    arsort($criteria_counter); // Trier par fréquence décroissante
    foreach ($criteria_counter as $criterion => $count) {
        if ($count >= 3) {
            $reference_criteria[] = $criterion;
        }
    }
    $reference_criteria = array_slice($reference_criteria, 0, $max_criteria);

    // Filtrer les specs partagées par au moins 2 produits (SANS LIMITE)
    $reference_specs = array();
    arsort($specs_counter); // Trier par fréquence décroissante
    foreach ($specs_counter as $spec => $count) {
        if ($count >= 2) {
            $reference_specs[] = $spec;
        }
    }
    // Pas de slice ici - on garde toutes les specs pour le toggle

    // ========================================
    // HELPERS
    // ========================================

    if (!function_exists('mlt_get_score_color_class')) {
        function mlt_get_score_color_class($score) {
            if ($score >= 75) return 'mlt-color-3';
            if ($score >= 50) return 'mlt-color-2';
            if ($score >= 25) return 'mlt-color-1';
            return 'mlt-color-0';
        }
    }

    if (!function_exists('mlt_format_score')) {
        function mlt_format_score($score) {
            return number_format($score / 10, 1);
        }
    }

    if (!function_exists('mlt_render_stars')) {
        function mlt_render_stars($score, $max_score) {
            // Meilleur score = 5 étoiles
            // -5 pts = -0.5 étoile
            // Minimum 1 étoile
            // Arrondi à la demi-étoile SUPÉRIEURE

            $diff = $max_score - $score;
            $stars_lost = $diff / 10; // -5 pts = -0.5 étoile
            $stars_raw = 5 - $stars_lost;

            // Arrondi à la demi-étoile supérieure (ceil)
            $stars = ceil($stars_raw * 2) / 2;
            $stars = max(1, min(5, $stars)); // Clamp entre 1 et 5

            $full = intval(floor($stars));
            $half = ($stars - $full) == 0.5 ? 1 : 0;
            $empty = 5 - $full - $half;

            // Utiliser Font Awesome (inclus dans Bricks Builder)
            $html = '<span class="mlt-stars">';
            for ($i = 0; $i < $full; $i++) {
                $html .= '<i class="fas fa-star mlt-star-full"></i>';
            }
            if ($half) {
                $html .= '<i class="fas fa-star-half-alt mlt-star-half"></i>';
            }
            for ($i = 0; $i < $empty; $i++) {
                $html .= '<i class="far fa-star mlt-star-empty"></i>';
            }
            $html .= '</span>';
            return $html;
        }
    }

    // Trouver le score max parmi tous les produits
    $max_score_global = 0;
    foreach ($products_data as $p) {
        if ($p['score_recent'] > $max_score_global) {
            $max_score_global = $p['score_recent'];
        }
    }

    // Calculer le nombre d'étoiles pour chaque produit (arrondi supérieur au 0.5)
    // À partir du rang 4, le maximum est 4.5 étoiles
    foreach ($products_data as &$p) {
        $diff = $max_score_global - $p['score_recent'];
        $stars_lost = $diff / 10;
        $stars_raw = 5 - $stars_lost;

        // Maximum selon le rang
        $max_stars = ($p['rank'] >= 4) ? 4.5 : 5;

        // Arrondi à la demi-étoile supérieure (ceil)
        $p['stars'] = max(1, min($max_stars, ceil($stars_raw * 2) / 2));
    }
    unset($p); // Nettoyer la référence

    $product_count = count($products_data);

    // Trouver le produit le moins cher (pour "Meilleur prix")
    $cheapest_rank = null;
    $cheapest_price = PHP_INT_MAX;
    $all_have_price = true;

    foreach ($products_data as $product) {
        if (!empty($product['prix_indicatif'])) {
            // Extraire le prix numérique (enlever espaces, virgules, etc.)
            $price_clean = preg_replace('/[^0-9,.]/', '', $product['prix_indicatif']);
            $price_clean = str_replace(',', '.', $price_clean);
            $price_num = floatval($price_clean);

            if ($price_num > 0 && $price_num < $cheapest_price) {
                $cheapest_price = $price_num;
                $cheapest_rank = $product['rank'];
            }
        } else {
            $all_have_price = false;
        }
    }

    // Le meilleur prix ne doit pas être le meilleur choix (rank 1)
    $show_best_price = $all_have_price && $cheapest_rank !== null && $cheapest_rank !== 1;

    // Si pas de meilleur prix possible, on affiche "Meilleure alternative" sur le #2
    $show_best_alternative = !$show_best_price;

    // ========================================
    // RENDU HTML
    // ========================================
    ?>

    <div class="mlt-comparison-wrapper">
        <table class="mlt-comparison-table">
            <tbody>

                <!-- 1. CLASSEMENT (avec bandeaux en :before/:after) -->
                <tr class="mlt-row mlt-row-first">
                    <td class="mlt-label-cell">Classement</td>
                    <?php foreach ($products_data as $product) :
                        // Couleur selon la place (1=vert foncé, 5=rouge)
                        $rank_colors = array(
                            1 => 'mlt-color-3', // vert
                            2 => 'mlt-color-2', // vert-jaune
                            3 => 'mlt-color-2', // vert-jaune
                            4 => 'mlt-color-1', // orange
                            5 => 'mlt-color-0'  // rouge
                        );
                        $rank_color = isset($rank_colors[$product['rank']]) ? $rank_colors[$product['rank']] : 'mlt-color-1';

                        // Déterminer la classe du bandeau
                        $banner_class = '';
                        if ($product['rank'] === 1) {
                            $banner_class = 'mlt-has-banner-best';
                        } elseif ($show_best_price && $product['rank'] === $cheapest_rank) {
                            $banner_class = 'mlt-has-banner-price';
                        } elseif ($show_best_alternative && $product['rank'] === 2) {
                            $banner_class = 'mlt-has-banner-alt';
                        }
                    ?>
                        <td class="mlt-product-cell mlt-rank-cell <?php echo $banner_class; ?>">
                            <span class="mlt-rank-badge <?php echo $rank_color; ?>"><?php echo $product['rank']; ?></span>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 2. IMAGE -->
                <tr class="mlt-row">
                    <td class="mlt-label-cell mlt-label-cell-header">
                        <img src="https://meilleurtest.fr/wp-content/uploads/2025/12/choix-de-la-redaction-v2.png"
                             alt="Comparatif indépendant"
                             class="mlt-header-badge">
                        <span class="mlt-header-subtext">Ce comparatif ne contient aucun produit sponsorisé</span>
                    </td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <a href="<?php echo esc_url($product['affiliate_url']); ?>" target="_blank" rel="nofollow sponsored noopener">
                                <?php if (!empty($product['image'])) : ?>
                                    <img src="<?php echo esc_url($product['image']); ?>"
                                         alt="<?php echo esc_attr($product['title']); ?>"
                                         class="mlt-product-img"
                                         loading="lazy">
                                <?php else : ?>
                                    <div class="mlt-product-img-placeholder"></div>
                                <?php endif; ?>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 3. NOM PRODUIT (lien affilié) -->
                <tr class="mlt-row mlt-sticky-header">
                    <td class="mlt-label-cell">Produit</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <a href="<?php echo esc_url($product['affiliate_url']); ?>"
                               class="mlt-product-name"
                               target="_blank"
                               rel="nofollow sponsored noopener">
                                <?php echo esc_html($product['title']); ?>
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 4. VERDICT -->
                <tr class="mlt-row">
                    <td class="mlt-label-cell">Verdict</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <?php if (!empty($product['label'])) : ?>
                                <span class="mlt-verdict-text"><?php echo esc_html($product['label']); ?></span>
                            <?php else : ?>
                                <span class="mlt-empty">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 5. SCORE GLOBAL (JAUGE HORIZONTALE) -->
                <tr class="mlt-row">
                    <td class="mlt-label-cell">Note globale</td>
                    <?php foreach ($products_data as $product) :
                        $score = $product['score_recent'] / 10; // Convertir 0-100 en 0-10
                        $max = 10;
                        $pct = min(100, max(0, intval($product['score_recent'])));

                        // Couleurs selon le score
                        $colors = array(
                            '#c30e0e',
                            '#c3590e',
                            '#AC8700',
                            '#66971B',
                            '#277c38',
                        );
                        $step = (int)min(floor($score / ($max / 5)), 4);
                        $mainColor = $colors[$step];

                        // Label selon le score (plages plus précises)
                        if ($score >= 9) {
                            $score_label = 'Exceptionnel';
                        } elseif ($score >= 8) {
                            $score_label = 'Excellent';
                        } elseif ($score >= 7) {
                            $score_label = 'Très bien';
                        } elseif ($score >= 6) {
                            $score_label = 'Bien';
                        } elseif ($score >= 5) {
                            $score_label = 'Moyen';
                        } elseif ($score >= 4) {
                            $score_label = 'Passable';
                        } elseif ($score >= 2) {
                            $score_label = 'Mauvais';
                        } else {
                            $score_label = 'Très mauvais';
                        }
                    ?>
                        <td class="mlt-product-cell">
                            <div class="mlt-score-gauge">
                                <span class="mlt-score-value" style="color: <?php echo $mainColor; ?>;"><?php echo round($score, 1); ?></span>
                                <div class="mlt-gauge-bar">
                                    <div class="mlt-gauge-fill" style="width: <?php echo $pct; ?>%; background: <?php echo $mainColor; ?>;"></div>
                                </div>
                                <span class="mlt-score-label" style="color: <?php echo $mainColor; ?>;"><?php echo $score_label; ?></span>
                            </div>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 7. SCORE (ÉTOILES) -->
                <tr class="mlt-row">
                    <td class="mlt-label-cell">Évaluation</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <?php echo mlt_render_stars($product['score_recent'], $max_score_global); ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 8. RÉSUMÉ PRODUIT -->
                <tr class="mlt-row mlt-row-valign-top">
                    <td class="mlt-label-cell">Résumé</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <?php if (!empty($product['resume'])) : ?>
                                <p class="mlt-resume"><?php echo wp_kses_post($product['resume']); ?></p>
                            <?php else : ?>
                                <span class="mlt-empty">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 10. POINTS POSITIFS -->
                <tr class="mlt-row mlt-row-valign-top">
                    <td class="mlt-label-cell">Positif</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <?php if (!empty($product['points_positifs'])) : ?>
                                <span class="mlt-points">
                                    <?php echo esc_html(implode(' • ', $product['points_positifs'])); ?>
                                </span>
                            <?php else : ?>
                                <span class="mlt-empty">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- 11. POINTS NÉGATIFS -->
                <tr class="mlt-row mlt-row-valign-top">
                    <td class="mlt-label-cell">Négatif</td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <?php if (!empty($product['points_negatifs'])) : ?>
                                <span class="mlt-points">
                                    <?php echo esc_html(implode(' • ', $product['points_negatifs'])); ?>
                                </span>
                            <?php else : ?>
                                <span class="mlt-empty">—</span>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                </tr>

                <!-- MEILLEURES OFFRES -->
				<tr class="mlt-row mlt-row-valign-top mlt-row-offres">
					<td class="mlt-label-cell">Meilleures offres</td>
					<?php foreach ($products_data as $product) :
						// Vérifier la disponibilité du prix
						$has_price = !empty($product['prix_indicatif']);

						// Compter les offres disponibles
						$has_amazon = !empty($product['asin_amazon']);
						$offres = array();

						// Offre Amazon
						if ($has_amazon) {
							$url_amazon = 'https://www.amazon.fr/dp/' . esc_attr($product['asin_amazon']) . '?tag=mlt00-21';

							// Déterminer le texte selon la présence du prix
							$texte_amazon = $has_price
								? $product['prix_indicatif'] . ' € sur Amazon'
								: 'Voir sur Amazon';

							$offres[] = array(
								'url' => $url_amazon,
								'texte' => $texte_amazon
							);
						}

						// Liens personnalisés
						foreach ($product['liens'] as $lien) {
							if (!empty($lien['url']) && !empty($lien['texte'])) {
								$offres[] = array(
									'url' => $lien['url'],
									'texte' => $lien['texte']
								);
							}
						}

						$nb_offres = count($offres);
					?>
						<td class="mlt-product-cell">
							<?php if ($has_price || $nb_offres > 0) : ?>
								<?php if ($nb_offres > 0) : ?>
									<div class="mlt-offres">
										<span class="mlt-offres-label"><?php echo $nb_offres > 1 ? 'Meilleures offres' : 'Meilleure offre'; ?></span>
										<?php foreach ($offres as $offre) : ?>
											<a href="<?php echo esc_url($offre['url']); ?>"
											   class="mlt-offre-link"
											   target="_blank"
											   rel="nofollow sponsored noopener">
												<?php echo esc_html($offre['texte']); ?>
											</a>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>

								<?php if ($has_price) : ?>
									<span class="mlt-price-note">*Prix constaté au moment de la rédaction</span>
								<?php endif; ?>
							<?php else : ?>
								<span class="mlt-empty">—</span>
							<?php endif; ?>
						</td>
					<?php endforeach; ?>
				</tr>

                <?php if (!empty($reference_criteria)) : ?>
                    <!-- SÉPARATEUR CRITÈRES -->
                    <tr class="mlt-row mlt-row-separator">
                        <td class="mlt-label-cell mlt-section-header" colspan="<?php echo $product_count + 1; ?>">
                            Notes par critère
                        </td>
                    </tr>

                    <!-- 9. SCORES CRITÈRES (JAUGES) -->
                    <?php foreach ($reference_criteria as $criterion) : ?>
                        <tr class="mlt-row">
                            <td class="mlt-label-cell"><?php echo esc_html($criterion); ?></td>
                            <?php foreach ($products_data as $product) :
                                $score = isset($product['criteria'][$criterion]) ? $product['criteria'][$criterion] : null;
                            ?>
                                <td class="mlt-product-cell">
                                    <?php if ($score !== null) :
                                        $display = mlt_format_score($score);
                                        $pct = min(100, max(0, intval($score)));
                                        $color_class = mlt_get_score_color_class($score);
                                    ?>
                                        <div class="mlt-criterion-gauge">
                                            <div class="mlt-gauge-bar">
                                                <div class="mlt-gauge-fill <?php echo $color_class; ?>" style="width: <?php echo $pct; ?>%;"></div>
                                            </div>
                                            <span class="mlt-criterion-value"><?php echo $display; ?></span>
                                        </div>
                                    <?php else : ?>
                                        <span class="mlt-empty">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($reference_specs)) :
                    $total_specs = count($reference_specs);
                    $has_hidden_specs = $total_specs > 10;
                ?>
                    <!-- SÉPARATEUR SPECS -->
                    <tr class="mlt-row mlt-row-separator">
                        <td class="mlt-label-cell mlt-section-header" colspan="<?php echo $product_count + 1; ?>">
                            Caractéristiques techniques
                        </td>
                    </tr>

                    <!-- CARACTÉRISTIQUES TECHNIQUES -->
                    <?php foreach ($reference_specs as $spec_index => $spec_name) :
                        $is_hidden = ($spec_index >= 10);
                    ?>
                        <tr class="mlt-row mlt-row-spec <?php echo $is_hidden ? 'mlt-spec-hidden' : ''; ?>">
                            <td class="mlt-label-cell"><?php echo esc_html($spec_name); ?></td>
                            <?php foreach ($products_data as $product) :
                                $spec_value = isset($product['specs'][$spec_name]) ? $product['specs'][$spec_name] : null;
                            ?>
                                <td class="mlt-product-cell">
                                    <?php if ($spec_value !== null) : ?>
                                        <span class="mlt-spec-value"><?php echo wp_kses_post($spec_value); ?></span>
                                    <?php else : ?>
                                        <span class="mlt-empty">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- LIEN VOIR NOTRE AVIS -->
                <tr class="mlt-row mlt-row-actions">
                    <td class="mlt-label-cell"></td>
                    <?php foreach ($products_data as $product) : ?>
                        <td class="mlt-product-cell">
                            <a href="#produit-n-<?php echo $product['rank']; ?>" class="mlt-btn mlt-btn-secondary">
                                Voir notre avis
                            </a>
                        </td>
                    <?php endforeach; ?>
                </tr>

            </tbody>
        </table>

        <?php if (!empty($reference_specs) && count($reference_specs) > 10) :
            $unique_id = 'mlt-toggle-' . uniqid();
            $wrapper_id = 'mlt-wrapper-' . uniqid();
        ?>
            <!-- BOUTON AFFICHER PLUS DE SPECS -->
            <div class="mlt-show-more-wrapper">
                <button type="button" class="mlt-btn-show-more" id="<?php echo $unique_id; ?>" data-wrapper="<?php echo $wrapper_id; ?>">
                    <span class="mlt-show-more-text">Afficher toutes les caractéristiques (<?php echo count($reference_specs); ?>)</span>
                    <span class="mlt-show-less-text" style="display: none;">Masquer les caractéristiques</span>
                    <i class="fas fa-chevron-down mlt-chevron"></i>
                </button>
            </div>

            <script>
            (function() {
                function initToggle() {
                    var btn = document.getElementById('<?php echo $unique_id; ?>');
                    if (!btn) {
                        setTimeout(initToggle, 100);
                        return;
                    }

                    var table = btn.closest('.mlt-comparison-wrapper').querySelector('.mlt-comparison-table');
                    if (!table) return;

                    var isExpanded = false;
                    var hiddenRows = table.querySelectorAll('.mlt-spec-hidden');
                    var showMoreText = btn.querySelector('.mlt-show-more-text');
                    var showLessText = btn.querySelector('.mlt-show-less-text');
                    var chevron = btn.querySelector('.mlt-chevron');

                    btn.onclick = function() {
                        isExpanded = !isExpanded;

                        for (var i = 0; i < hiddenRows.length; i++) {
                            hiddenRows[i].style.display = isExpanded ? 'table-row' : 'none';
                        }

                        showMoreText.style.display = isExpanded ? 'none' : 'inline';
                        showLessText.style.display = isExpanded ? 'inline' : 'none';
                        chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                    };
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', initToggle);
                } else {
                    initToggle();
                }
            })();
            </script>
        <?php endif; ?>
    </div>

    <?php

} catch (Exception $e) {
    if ($debug_mode) {
        echo "<script>console.error('Erreur: " . addslashes($e->getMessage()) . "');</script>";
    }
}
?>
