#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script pour générer un favicon.ico avec un grand "E"
 * Style neumorphism : fond #e6e7ee, texte #44476A (couleur du texte de la page)
 */

chdir(dirname(__DIR__));

// Couleurs du thème neumorphism
$bgColor = [230, 231, 238]; // #e6e7ee (fond)
$textColor = [68, 71, 106]; // #44476A (couleur du texte de la page)

// Taille du favicon (32x32 est une bonne taille standard)
$size = 32;

// Créer une image
$image = imagecreatetruecolor($size, $size);

// Allouer les couleurs
$bg = imagecolorallocate($image, $bgColor[0], $bgColor[1], $bgColor[2]);
$text = imagecolorallocate($image, $textColor[0], $textColor[1], $textColor[2]);

// Remplir le fond
imagefill($image, 0, 0, $bg);

// Dessiner un "E" avec des rectangles (style épais et moderne)
$thickness = max(4, (int)($size / 7)); // Plus épais pour être visible
$margin = (int)($size * 0.15); // Moins de marge pour un E plus grand
$barWidth = (int)($size * 0.6); // Plus large

// Barre verticale gauche
imagefilledrectangle($image, 
    $margin, 
    $margin, 
    $margin + $thickness, 
    $size - $margin, 
    $text
);

// Barre horizontale du haut
imagefilledrectangle($image, 
    $margin, 
    $margin, 
    $margin + $barWidth, 
    $margin + $thickness, 
    $text
);

// Barre horizontale du milieu (un peu plus courte)
imagefilledrectangle($image, 
    $margin, 
    (int)($size / 2) - (int)($thickness / 2), 
    $margin + (int)($barWidth * 0.75), 
    (int)($size / 2) + (int)($thickness / 2), 
    $text
);

// Barre horizontale du bas
imagefilledrectangle($image, 
    $margin, 
    $size - $margin - $thickness, 
    $margin + $barWidth, 
    $size - $margin, 
    $text
);

// Sauvegarder en PNG d'abord (plus simple)
$pngPath = __DIR__ . '/../public/favicon.png';
imagepng($image, $pngPath);
imagedestroy($image);

// Créer un fichier ICO simple (format ICO basique)
// Pour un vrai ICO, on peut utiliser ImageMagick ou simplement utiliser le PNG
// La plupart des navigateurs modernes acceptent aussi les PNG comme favicon

// Copier le PNG comme ICO (les navigateurs modernes l'acceptent)
$icoPath = __DIR__ . '/../public/favicon.ico';
copy($pngPath, $icoPath);

echo "Favicon généré avec succès :\n";
echo "  - PNG: $pngPath (" . filesize($pngPath) . " bytes)\n";
echo "  - ICO: $icoPath (" . filesize($icoPath) . " bytes)\n";
