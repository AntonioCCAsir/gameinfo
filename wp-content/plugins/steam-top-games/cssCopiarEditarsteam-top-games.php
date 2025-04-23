<?php
/*
Plugin Name: Steam Top Games
Description: Muestra diferentes categorías de juegos de Steam usando un shortcode.
Version: 1.1
Author: Tu Nombre
*/

// Función base para obtener detalles del juego
function steam_get_game_details($appid) {
    $url = "https://store.steampowered.com/api/appdetails?appids=$appid";
    $json = @file_get_contents($url);
    if ($json === false) return ['name' => "Desconocido", 'image' => '', 'price' => 0, 'discount' => 0];

    $data = json_decode($json, true);
    if (!isset($data[$appid]['success']) || !$data[$appid]['success']) {
        return ['name' => "Desconocido", 'image' => '', 'price' => 0, 'discount' => 0];
    }

    $game_data = $data[$appid]['data'];
    return [
        'name' => $game_data['name'],
        'image' => $game_data['header_image'],
        'price' => $game_data['price_overview']['final'] ?? 0,
        'discount' => $game_data['price_overview']['discount_percent'] ?? 0,
        'release_date' => $game_data['release_date']['date'] ?? ''
    ];
}

// Juegos más jugados
function steam_get_most_played_games() {
    $url = "https://api.steampowered.com/ISteamChartsService/GetMostPlayedGames/v1/";
    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return $data['response']['ranks'] ?? [];
}

// Juegos en tendencia (usaremos los más vendidos como proxy)
function steam_get_trending_games() {
    $url = "https://store.steampowered.com/api/featuredcategories/";
    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return array_slice($data['top_sellers']['items'] ?? [], 0, 10);
}

// Juegos con mayores descuentos
function steam_get_discounted_games() {
    $url = "https://store.steampowered.com/api/featuredcategories/";
    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    $specials = $data['specials']['items'] ?? [];
    usort($specials, function($a, $b) {
        return $b['discount_percent'] - $a['discount_percent'];
    });
    return array_slice($specials, 0, 10);
}

// Nuevos lanzamientos
function steam_get_new_releases() {
    $url = "https://store.steampowered.com/api/featuredcategories/";
    $json = @file_get_contents($url);
    if ($json === false) return [];
    $data = json_decode($json, true);
    return array_slice($data['new_releases']['items'] ?? [], 0, 10);
}

function steam_top_games_shortcode() {
    // Preparar datos para cada sección
    $most_played = [];
    foreach (array_slice(steam_get_most_played_games(), 0, 10) as $juego) {
        $appid = $juego['appid'];
        $details = steam_get_game_details($appid);
        $most_played[] = [
            'appid' => $appid,
            'nombre' => $details['name'],
            'image' => $details['image'],
            'peak' => $juego['peak_in_game']
        ];
    }

    $trending = [];
    foreach (steam_get_trending_games() as $juego) {
        $trending[] = [
            'appid' => $juego['id'],
            'nombre' => $juego['name'],
            'image' => $juego['header_image'],
            'price' => $juego['final_price']
        ];
    }

    $discounted = [];
    foreach (steam_get_discounted_games() as $juego) {
        $discounted[] = [
            'appid' => $juego['id'],
            'nombre' => $juego['name'],
            'image' => $juego['header_image'],
            'discount' => $juego['discount_percent'],
            'price' => $juego['final_price']
        ];
    }

    $new_releases = [];
    foreach (steam_get_new_releases() as $juego) {
        $new_releases[] = [
            'appid' => $juego['id'],
            'nombre' => $juego['name'],
            'image' => $juego['header_image'],
            'release_date' => $juego['release_date'] ?? 'N/A'
        ];
    }

    ob_start(); ?>
    <div class="steam-top-games">
        <style>
            .steam-top-games table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                background-color: #fff;
            }
            .steam-top-games th,
            .steam-top-games td {
                padding: 12px;
                text-align: left;
                border: 1px solid #ddd;
            }
            .steam-top-games th {
                background-color: #1b2838;
                color: white;
                font-weight: bold;
            }
            .steam-top-games tr:nth-child(even) {
                background-color: #f2f2f2;
            }
            .steam-top-games tr:hover {
                background-color: #ddd;
            }
            .steam-top-games img {
                max-width: 100px;
                height: auto;
                display: block;
            }
            .steam-top-games a {
                color: #1b2838;
                text-decoration: none;
            }
            .steam-top-games a:hover {
                text-decoration: underline;
            }
            .steam-top-games h2 {
                color: #1b2838;
                margin: 30px 0 15px;
            }
        </style>

        <!-- Juegos Más Jugados -->
        <h2>Top 10 Juegos Más Jugados</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Imagen</th>
                    <th>Juego</th>
                    <th>Jugadores Pico</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($most_played as $index => $j): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><img src="<?php echo esc_url($j['image']); ?>" alt="<?php echo esc_attr($j['nombre']); ?>"></td>
                        <td><a href="https://store.steampowered.com/app/<?php echo esc_attr($j['appid']); ?>" target="_blank"><?php echo esc_html($j['nombre']); ?></a></td>
                        <td><?php echo number_format($j['peak']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Juegos en Tendencia -->
        <h2>Juegos en Tendencia</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Imagen</th>
                    <th>Juego</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($trending as $index => $j): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><img src="<?php echo esc_url($j['image']); ?>" alt="<?php echo esc_attr($j['nombre']); ?>"></td>
                        <td><a href="https://store.steampowered.com/app/<?php echo esc_attr($j['appid']); ?>" target="_blank"><?php echo esc_html($j['nombre']); ?></a></td>
                        <td><?php echo number_format($j['price'] / 100, 2); ?>€</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Juegos con Mayor Descuento -->
        <h2>Juegos con Mayor Descuento</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Imagen</th>
                    <th>Juego</th>
                    <th>Descuento</th>
                    <th>Precio</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discounted as $index => $j): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><img src="<?php echo esc_url($j['image']); ?>" alt="<?php echo esc_attr($j['nombre']); ?>"></td>
                        <td><a href="https://store.steampowered.com/app/<?php echo esc_attr($j['appid']); ?>" target="_blank"><?php echo esc_html($j['nombre']); ?></a></td>
                        <td><?php echo esc_html($j['discount']); ?>%</td>
                        <td><?php echo number_format($j['price'] / 100, 2); ?>€</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Nuevos Lanzamientos -->
        <h2>Nuevos Lanzamientos</h2>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Imagen</th>
                    <th>Juego</th>
                    <th>Fecha Lanzamiento</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($new_releases as $index => $j): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><img src="<?php echo esc_url($j['image']); ?>" alt="<?php echo esc_attr($j['nombre']); ?>"></td>
                        <td><a href="https://store.steampowered.com/app/<?php echo esc_attr($j['appid']); ?>" target="_blank"><?php echo esc_html($j['nombre']); ?></a></td>
                        <td><?php echo esc_html($j['release_date']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('steam_top_games', 'steam_top_games_shortcode');