<?php
session_start();

// Récupère les données POST
if(isset($_POST['motWiki']) && $_POST['motWiki'] != ''){
    // Supprime data si déjà présentes
    if(isset($data) && $data != ''){
        unset($data);
    }

    // ############################################################
    //      TRAVAIL PREPARATOIRE
    // ############################################################

    // Ajout des fichiers PHP
    require "simple_html_dom.php";

    // Fonction d'affichage
    function printPre($x) {
        echo '<pre>';
        print_r($x);
        echo '</pre>';
    }

    // Fonction de validation des données
    function valid_donnees($donnees) {
        $donnees = trim($donnees);
        $donnees = stripslashes($donnees);
        $donnees = htmlspecialchars($donnees);
        return $donnees;
    }

    // Fonction pour enlever certains tags REMPLACE <a></a> PAR <reference></reference>: permettre de rediriger les mots Remède ainsi que les autres pages.
    function enleveTagsPerso($chaine) {
        $search  = array('<i>', '</i>', '<ol>', '</ol>', '<li>', '</li>', '<pre>', '</pre>', '<a', '</a>');
        $replace = array('', '', '', '', '', '', '', '', '<reference', '</reference>');

        $chaine_html = new simple_html_dom();
        $chaine_html->load(str_replace($search, $replace, $chaine));
        return clearTags($chaine_html)->outertext;
    }

    function isBannedTag($el) {
        return (!str_starts_with($el->outertext, '<i>') && !str_starts_with($el->outertext, '<b>') && !isCustomTag($el)) && str_starts_with($el->outertext, '<');
    }

    function isItalicOrBold($el) {
        return str_starts_with($el->outertext, '<i>') || str_starts_with($el->outertext, '<b>');
    }

    function isCustomTag($el) {
        return str_starts_with($el->outertext, '<reference') || str_starts_with($el->outertext, '<phoneme');
    }

    function isUselessLink($link) {
        return str_starts_with($link, 'https://en.wiktionary.org/wiki/Appendix') || str_starts_with($link, '/wiki/Appendix');
    }

    // Enlève tous les tags sauf les <i>, <b> <reference et <phoneme>
    function clearTags($chaine) {

        // On supprime les sources si c'est un exemple
        $sourcesEl = $chaine->find('.cited-source', 0);
        if (null != $sourcesEl) {
            $sourcesEl->outertext = '';
            $inner = $chaine->innertext;
            $chaine = new simple_html_dom();
            $chaine->load($inner);
        }

        // On itère tous les tags
        $all_tags = $chaine->find('*');
        foreach ($all_tags as $tag) {
            // On passe si c'est un <i> ou <b>
            if (isItalicOrBold($tag)) {
                continue;
            }
            // On passe si c'est un tag custom
            if (isCustomTag($tag)) {
                continue;
            }
            $tag->outertext = $tag->innertext;
        }

        if (!isset($chaine->load)) {
            $save_html = new simple_html_dom();
            $save_html->load($chaine->outertext);
            $chaine = $save_html;
        } else {
            $chaine->load($chaine->save());
        }
        // On récupère les tags restants
        $all_tags = array_filter($chaine->find('*'), "isBannedTag");
        // Récursive pour tout supprimer
        if (sizeof($all_tags) > 0) {
            $chaine = clearTags($chaine);
        }
        // Juste avant on clean les tags restant de leurs propriétés inutiles
        return clearAttributes($chaine);
    }

    // Supprime toutes les attributs (title, alt ect...) sauf "href"
    function clearAttributes($chaine) {
        // Garder juste le mot dans le href
        // Pour cela on remplace les /wiki/ et autres urls par une chaine vide
        $to_replace = ['wiki/', 'https://en.wiktionary.org', '/'];
        $to_replace_by = ['', '', ''];

        // On itère tous les tags
        foreach ($chaine->find('*') as $tag) {
            // On sauvegarde l'href, sans l'ancre, et on lui applique des modifs
            $href = explode('#', $tag->getAttribute('href'))[0];

            // Si c'est un lien pou créer une page inexistante, on supprimer le lien.
            $title = $tag->getAttribute('title');
            if (str_contains($title, 'page inexistante')) $href = '';

            // On itère tous les attributs de l'élement
            foreach ($tag->getAllAttributes() as $attr => $val) {
                $tag->removeAttribute($attr);
            }
            // Si href non-vide et autre qu'une annexe, on remet l'attr href
            if ('' != $href && !isUselessLink($href)) $tag->setAttribute('href', str_replace($to_replace, $to_replace_by, $href));
        }

        // On supprime le tag custom <reference> si il n'a pas d'attribut href
        $all_references = $chaine->find('reference');
        foreach ($all_references as $tag) {
            if (null == $tag->getAttribute('href') || '' == $tag->getAttribute('href') || 1 == $tag->getAttribute('href')) $tag->outertext = $tag->innertext;
        }

        if (!isset($chaine->load)) {
            $save_html = new simple_html_dom();
            $save_html->load($chaine->outertext);
            $chaine = $save_html;
        } else {
            $chaine->load($chaine->save());
        }
        return $chaine;
    }

    // Fonction qui vérifie l'existence d'une page web
    function IFilesExists($url) {
        $headers = @get_headers($url, 1);
        if ($headers[0] == '') return false;
        return !((preg_match('/404/', $headers[0])) == 1);
    }

    // Nettoyer les données passées en POST
    $motWikiTemp = $_POST['motWiki'];
    $motWiki = valid_donnees($motWikiTemp);


    /* ############################################################################################# */
    // Définir et Initialiser les variables
    $url = '';                  // url Wikitionary du mot recherché
    $error= '';                 // msg erreur renvoyé à l'utilisateur
    $naturesGram = [];          // tab des classes grammaticales du mot recherché
    $url_img = '';              // url de l'illustration si présente sur Wikitionnaire
    $url_credits = '';          // url de credit de la photo
    $legende_img = '';          // légende de l'image
    $resfinal = [];             // tableau final des résultats
    $nbNaturesGram = 0;         // Nb de natures grammaticales
    $resFin = [];               // tableau de résultat temp (résultat pour une classe grammaticale)
    $genre = [];                // tableau de genre pour la classe "nom commun"
    $etymologies = [];          // étymologies du mot
    $plurals = [];              // les pluriels du mot
    /* ############################################################################################### */

    // Messsage si pas de page Wikitionnaire
    if (IFilesExists("https://en.wiktionary.org/wiki/".$motWiki)){
        $url = "https://en.wiktionary.org/wiki/".$motWiki;

        // ############################################################
        //      DEBUT DU PARSING
        // ############################################################

        // Parser l'ensemble de la page
        $html = new simple_html_dom();
        $html->load_file($url);
        $found_plurals = [];

        $sectionTitleQuery = ".mw-heading [id^=Verb], .mw-heading [id^=Noun], .mw-heading [id^=Adjective], .mw-heading [id^=Adverb], .mw-heading [id^=Interjection], .mw-heading [id^=Preposition], .mw-heading [id^=Conjunction], .mw-heading [id^=Pronoun]";

        // Cherche les span spécifiques aux classes grammaticales
        $nbNaturesGram=count($html->find($sectionTitleQuery));

        // Si il y a une ou des classes grammaticales
        if($nbNaturesGram != 0){
            // On récupère le texte des classes grammaticales
            foreach($html->find($sectionTitleQuery) as $v){
                if ($v->parent->next_sibling()->find('.headword', 0)->getAttribute('lang') != 'en') continue;
                $naturesGram[] = $v->plaintext;
            }
            $nbNaturesGram = count($naturesGram);

            // On récupère le genre pour la classe de "nom commun"
            $z=0;
            # Pas de genre en anglais, on met donc les natures
            $genre = $naturesGram;

            // ############################################################
            //      DEFINITIONS POUR CHAQUE CLASSE GRAMMATICALE
            // ############################################################

            for($z=0; $z<$nbNaturesGram; $z++){

                // Détermine à partir de quelle liste <ol> commence les définitions
                if($z == 0){
                    $teteOk = false;
                    $ol_rang = 0;

                    // On exclut les listes de symbole
                    if(null != $html->find('div[id=mw-content-text]', 0)){
                        if(null != $html->find('div[id=mw-content-text]', 0)->find('dl', 0)){
                            if(null != $html->find('div[id=mw-content-text]', 0)->find('dl', 0)->find('ol', 0)){
                                $nbOl = count($html->find('div[id=mw-content-text]', 0)->find('dl', 0)->find('ol'));
                                $ol_rang = $nbOl;
                            }
                        }
                    }

                    // On ajoute les etymologies dans la liste
                    // La section des étymologies est juste après le parent du titre de la section #Etymology
                    if (null != $html->find('#Etymology', 0)) {
                        $sections = $html->find('#Etymology');
                        foreach ($sections as $sectionTitle) {
                            $etymologyContent = $sectionTitle->parent->next_sibling();
                            if (null != $etymologyContent) {
                                $contenu = enleveTagsPerso($etymologyContent->innertext);
                                $etymologies[] = $contenu;
                            }
                        }
                    }

                    // On exclut les listes d'éthymologie
                    if(null != $html->find('ol', $ol_rang)){
                        if(null != $html->find('ol', $ol_rang)->find('li', 0)){
                            if(null != $html->find('ol', $ol_rang)->find('li', 0)->find('span', 0)){
                                if($html->find('ol', $ol_rang)->find('li', 0)->find('span', 0)->plaintext == 'Linguistique'){
                                    $ol_rang++;
                                }
                            }
                        }
                    }

                }

                // on obtient $ol_rang : rang de la première liste à parser
                if($z != 0){
                    $ol_rang++;
                }

                // ############################################################
                //      PLURIELS DU MOT
                // ############################################################
                if (null != $html->find('.Latn.form-of.lang-en.p-form-of')) {
                    // Il y a des pluriels
                    foreach ($html->find('.Latn.form-of.lang-en.p-form-of') as $el) {
                        $plural = $el->plaintext;
                        if (in_array($plural, $found_plurals)) continue;
                        $found_plurals[] = $plural;
                        $plurals[] = [
                            "label" => "",
                            "singular" => $motWiki,
                            "plural" => $plural
                        ];
                    }
                }

                // Parse la 1ère liste
                $tete=$html->find('ol', $ol_rang);

                // Si on trouve une liste ol de définition
                if($tete != ''){
                    if (null != $tete->prev_sibling()) {
                        if ($tete->prev_sibling()->find('.headword', 0)->getAttribute('lang') != 'en') continue;
                    }
                    $str='';
                    $str2='';
                    $ref='';
                    // On créer une liste d'exemples
                    $exemples = [];

                    // On détermine les <ul> relatives aux exemples et on rempli la liste $exemples
                    $html3 = new simple_html_dom();
                    $html3->load($tete);
                    foreach($html3->find('ul') as $ul){
                        // On itère les exemples
                        foreach($ul->find('li') as $li){
                            // Si le wiki propose d'ajouter un exemple, on passe
                            if (null != $li->find('a', 0)) {
                                if ($li->find('a', 0) ->innertext == 'Ajouter un exemple') continue;
                            }

                            // On récupère le contenu de l'exemple (tout le contenu du li sans la source) puis la source (li.sources, sans le span.tiret)
                            $contenu = $li;

                            // On garde les sources
                            $sourcesEl = $li->find('span.cited-source', 0);
                            $sources = "";
                            if (null != $sourcesEl) {
                                // On supprime les liens
                                $anchors = $sourcesEl->find('a');
                                foreach ($anchors as $a) {
                                    $a->outertext = $a->innertext;
                                }
                                $sources = str_replace(['<span class="tiret">', '</span>'], ['', ''], $sourcesEl->innertext);
                            }

                            // On ajoute l'exemple à la liste
                            $exemples[] = [
                                "contenu" => clearTags($contenu)->innertext,
                                "sources" => $sources
                            ];
                        }
                        $ul->innertext="";
                    }

                    // Cas particulier des Formes de verbes :
                    // - on détermine le verbe à l'infinitif et on crée un lien vers celui-ci
                    if($naturesGram[$z] == "Verb" && null != $html3->find('.form-of-definition-link')){
                        $needLink = false;
                        $isPluriel = false;
                        foreach($html3->find('.form-of-definition-link') as $p => $a){
                            $a->id = "complement_verbe_".$p;
                            $radical = $a->title;
                            if ($radical != $motWiki){
                                $needLink = true;
                                $a->href = "https://en.wiktionary.org/wiki/".$radical;
                            }
                        }
                    }

                    // Cas particulier des autres formes :
                    // - on détermine si c'est le pluriel qui est défini : dans ce cas, on crée un lien vers le mot au singulier
                    // TODO
                    if($naturesGram[$z] != "Verb"){
                        $isPluriel = false;
                        $plurielTest = $html3->find('li', 0);
                        $text=strip_tags($plurielTest);
                        $html3Tab = explode(" ", $text);

                        foreach($html3Tab as $mot){
                            if($mot == "Pluriel" || $mot == "pluriel" || (strpos($text, "Féminin singulier") !== FALSE)){
                                $linkSingulier = $html3->find('a', 0);
                                $singulier = $linkSingulier->title;
                                $linkSingulier->href = "https://en.wiktionary.org/wiki/".$singulier;
                                $linkSingulier->id = "complement_genre";
                                $linkSingulier->class = "link-perso click-def-complement";
                                $isPluriel = true;
                            }
                        }
                    }

                    // ############################################################
                    //      METHODE UTILISEE :
                    //          - on va déterminer toutes les <li> dans les <ol> qui restent;
                    //          - va falloir repérer les sous listes, les <ol> présentes dans
                    //      les <ol> que l'on parse.
                    //
                    //
                    //      Méthode choisie : faire la différence entre :
                    //
                    //          ** tab de tous les li (si sous liste "ol", il y aura une "li" qui
                    //      qui contiendra "l'intitulé" et l'"ol" avec ses "li" ) :
                    //
                    //      <li>
                    //          Intitulé
                    //          <ol>
                    //              <li>l1 de la sous-liste "ol"</li>
                    //              <li>l2 de la sous-liste "ol"</li>
                    //          </ol>
                    //      </li>
                    //
                    //         ** tab des li des sous-listes <ol>
                    //
                    //
                    // ############################################################


                    $str=$html3;
                    // On supprime les <ul> relatives aux exemples
                    $str2  = str_replace("<ul></ul>", "", $str);

                    $html4 = new simple_html_dom();
                    $html4->load($str2);

                    // Variables temporaires pour effectuer le tri
                    $t = $html4->find('ol', 0);

                    $ref = $t->innertext;

                    $resTep = [];
                    $resOl = [];
                    $resT = [];
                    $resTT = [];
                    $res2 = [];
                    $res = [];
                    $resTTT = [];
                    $resTemmp = [];
                    $resTemmp2 = [];
                    $resFin = [];
                    $lastDef='';

                    // Tableau de tous les <li>
                    $html5 = new simple_html_dom();
                    $html5->load($ref);
                    $testLi=$html5->find('li');
                    foreach($testLi as $li){
                        $res[]=$li;
                    }

                    // verifie si il y a une "ol" dans les "li"
                    foreach($res as $v){
                        $test = $v->find('ol', 0);
                        if($test != ''){
                            foreach($test->find('li') as $li){
                                $resTep[]=$li;
                            }
                        }

                    }

                    // Si "ol" dans "li", on fait la différence
                    $res = array_diff($res, $resTep);

                    $resStr = '';

                    // On réorganise les résultats obtenus
                    foreach($res as $v){
                        $resStr .= $v;
                    }

                    $resOl = explode("<ol>", $resStr);

                    $nbEl = count($resOl);

                    /* ###################################################
                        On organise de la la façon suivante:
                            si une sous-liste "ol" : [intitulé, li_1, li_2] par exemple
                            si pas de sous liste : li
                    */ ###################################################

                    if ($nbEl == 1){
                        $resTemmp=explode("<li>", $resOl[0]);
                            foreach($resTemmp as $l){
                                $res2[]=$l;
                            }
                    }else{
                        foreach($resOl as $i => $v){
                            if (strpos($v, "</ol>") !== FALSE){
                                $resTTT=explode("</ol>", $v);

                                $resTemmp=explode("<li>", $resTTT[0]);
                                foreach($resTemmp as $l){
                                    $resT[]=$l;
                                }
                                $res2[]=[$resT];

                                $resTemmp2=explode("<li>", $resTTT[1]);
                                foreach($resTemmp2 as $l2){
                                    $res2[]=$l2;
                                }

                            }else{
                                $resTemmp=explode("<li>", $v);
                                foreach($resTemmp as $l){
                                    $res2[]=$l;
                                } // TODO
                            }
                        }
                    }

                    // On supprime les tags html et on supprime les doublons
                    $nbArray=0;
                    foreach($res2 as $i => $v){
                        if(is_string($v)){
                            // sauf pour les cas particuliers (verbes conjugués et pluriels)
                            if(($naturesGram[$z] == "Forme de verbe" && $needLink) || $isPluriel == true){
                                $resFin[] = enleveTagsPerso($v);
                            }else{
                                $resFin[] = strip_tags($v);
                            }
                            if($resFin[$i] == ''){
                                unset($resFin[$i]);
                            }
                        }
                        if(is_array($v)){
                            $nbArray++;
                            foreach($v[0] as $p => $l){
                                if($p != 0){
                                    $resTT[]=strip_tags($l);
                                }
                            }
                            if ($p != 0 && $nbArray>1){
                                $restTTUnique =array_values(array_unique($resTT));
                                $k=array_search("", $restTTUnique);
                                $resFin[]=[array_slice($restTTUnique, $k+1)];
                            }else{

                                $resFin[]=[array_keys(array_flip($resTT))];
                            }

                        }

                    }
                    $ol_rang += $nbArray;

                    // Le résultat de la classe grammaticale z $resTT est ajouté au tab resFinal
                    $resfinal[$z][] = $resFin;

                    // Les exemples sont ajoutés au tableau resFinal
                    $resfinal[$z][] = $exemples;

                // Si on ne trouve pas de liste ol dans la classe grammaticale z
                }else{
                    $error = "Un problème est survenu lors de la recherche de la définition du mot ".strtoupper($motWiki);
                }

            }

        // Si on ne trouve pas de classes grammaticales
        }else{
            $error = "Un problème est survenu lors de la recherche de la définition du mot ".strtoupper($motWiki);
        }

    // Si le mot saisi n'est pas présent sur Wikitionnaire
    } else {
        $error = "Le mot ".strtoupper($motWiki). " n'apparait pas dans notre dictionnaire de référence, le Wiktionary.";
    }


    // Tableau final
    $data = array();
    $data["motWiki"]=$motWiki;
    $data["error"]=$error;
    $data["direct_link"]=$url;
    $data["url_img"]=$url_img;
    $data["legende_img"]=$legende_img;
    $data["url_credits"]=$url_credits;
    $data["nature"]=$naturesGram;
    $data["genre"]=$genre;
    $data["etymologies"]=$etymologies;
    $data["plurals"]=$plurals;
    $data["natureDef"]=$resfinal;

    // Encodage du tableau au format JSON
    echo json_encode($data);
}
