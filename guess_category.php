<?php

$regex_to_genre = array(
    
    "/thriller|krimi|bűnügy/" => "Detective / Thriller",
    "/kaland|western|háború/" => "Adventure / Western / War",
    "/sci\-fi|scifi|science fiction|fantasy|fantasztikus|horror/" => "Science fiction / Fantasy / Horror",
    "/vígjáték|komédia/" => "Comedy",
    "/melodráma|folklór/" => "Soap / Melodrama / Folkloric",
    "/romantik/" => "Romance",
    "/klasszikus|vallás|történelmi|dráma/" => "Serious / Classical / Religious / Historical movie / Drama",
    "/felnőtt/" => "Adult movie / Drama",
    "/időjárás/" => "News / Weather report",
    "/hírmagazin/" => "News magazine",
    "/hír|aktuális ügyek|aktualitások/" => "News / Current affairs",
    "/dokumentumfilm/" => "Documentary",
    "/interjú|vita|beszélgetés/" => "Discussion / Interview / Debate",
    "/játék/" => "Show / Game show",
    "/kvíz|vetélkedő/" => "Game show / Quiz / Contest",
    "/varieté/" => "Variety show",
    "/talk show|talkshow|show\-műsor|beszélgetős műsor/" => "Talk show",
    "/olimpia|bajnokság/" => "Special events (Olympic Games, World Cup, etc.)",
    "/sportmagazin/" => "Sports magazines",
    "/futball|foci|labdarúg/" => "Football / Soccer",
    "/tenisz|tennis|fallabda|squash/" => "Tennis / Squash",
    "/csapatjáték/" => "Team sports (excluding football)",
    "/atlétika|atletika/" => "Athletics",
    "/motor sport/" => "Motor sport",
    "/vízi sport|úsz|vízilabda/" => "Water sport",
    "/sport/" => "Sports",
    "/gyerek|gyermek|ifjúsági/" => "Children's / Youth programs",
    "/óvoda|bölcsöde/" => "Pre-school children's programs",
    // "" => "Entertainment programs for 6 to 14",
    // "" => "Entertainment programs for 10 to 16",
    "/oktatás|iskola/" => "Informational / Educational / School programs",
    "/animáció|rajzfilm/" => "Cartoons / Puppets",
    "/balett|tánc/" => "Music / Ballet / Dance",
    "/rock|pop/" => "Rock / Pop",
    "/klasszikus zene|komolyzene/" => "Serious music / Classical music",
    "/folk|népzene/" => "Folk / Traditional music",
    "/jazz/" => "Jazz",
    "/musical|opera/" => "Musical / Opera",
    "/művészet/" => "Arts / Culture without music",
    "/előadóművészet/" => "Performing arts",
    "/szépművészet/" => "Fine arts",
    "/egyház|hitélet/" => "Religion",
    "/kultúra/" => "Popular culture / Traditional arts",
    "/irodalom/" => "Literature",
    "/mozi/" => "Film / Cinema",
    "/kísérleti film|videó/" => "Experimental film / Video",
    "/újság/" => "Broadcasting / Press",
    "/közélet|politika|gazdaság/" => "Social / Political issues / Economics",
    "/magazin|riport|dokument/" => "Magazines / Reports / Documentary",
    "/szociális/" => "Economics / Social advisory",
    // "" => "Remarkable people",
    "/képzés|tudomány/" => "Education / Science / Factual topics",
    "/természet|állat|környezet/" => "Nature / Animals / Environment",
    "/technológia|természettudomány/" => "Technology / Natural sciences",
    "/orvostudomány|orvosság|gyógyszer|pszichológia/" => "Medicine / Physiology / Psychology",
    "/külföld|felfedezés/" => "Foreign countries / Expeditions",
    "/spirituális/" => "Social / Spiritual sciences",
    // "" => "Further education",
    "/nyelv/" => "Languages",
    "/szabadidő|hobbi|hobby/" => "Leisure hobbies",
    "/turizmus|turista|utazás/" => "Tourism / Travel",
    "/kézműves/" => "Handicraft",
    "/autó/" => "Motoring",
    "/fitnesz|egészség/" => "Fitness and health",
    "/főző|konyha|gastro|gasztro/" => "Cooking",
    "/reklám|vásárlás/" => "Advertisement / Shopping",
    "/kert/" => "Gardening",
    "/film|sorozat/" => "Movie / Drama"
);

function guess_genre_category($text)
{
    global $regex_to_genre;
    
    if (!$text) {
        return "";
    }
    foreach ($regex_to_genre as $key => $value) {
        if (preg_match($key, $text)) {
            return $value;
        }
    }
    return "";
}