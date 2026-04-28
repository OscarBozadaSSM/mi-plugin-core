<?php
/*
Plugin Name: Mi Plugin Core
Description: Lógica central del sistema
Version: 1.0.0
*/

if (!defined('ABSPATH')) exit;

// 🔥 UPDATE CHECKER
require_once __DIR__ . '/plugin-update-checker-master/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/OscarBozadaSSM/mi-plugin-core/',
    __FILE__,
    'mi-plugin-core'
);

$updateChecker->setBranch('main');

// Ejemplo básico
add_action('init', function() {
    // Aquí irá tu lógica
}); 

add_shortcode('saludo', function() {
    return 'Hola desde el plugin';
});


function render_tabla($titulo, $tabla, $rows, $mostrar_ids = false) {
    global $wpdb;

    if (!$rows) return "<h3>$titulo</h3><p>Sin registros</p>";

    $columns = $wpdb->get_results("SHOW COLUMNS FROM `$tabla`");

    $tinyint_fields = [];

    foreach ($columns as $col) {
        if (strpos($col->Type, 'tinyint') !== false) {
            $tinyint_fields[] = strtolower(trim($col->Field));
        }
    }

    $id_unico = uniqid();

    $html = "
    <div style='width:800px; margin:20px auto; border:1px solid #ccc; border-radius:5px; overflow:hidden;'>

        <div onclick=\"toggleSeccion('$id_unico')\" 
            style='background:#333;color:#fff;padding:10px;cursor:pointer;'>
            $titulo
        </div>

        <div id='$id_unico' style='".($titulo === "PERSONAL" ? "display:block;" : "display:none;")."'>
    ";

    $html .= '
    <table style="width:800px; margin:20px auto; border-collapse:collapse; table-layout:fixed;">
        <colgroup>
            <col style="width:30%;">
            <col style="width:70%;">
        </colgroup>
    ';

    foreach ($rows as $row) {
        foreach ($row as $k => $v) {

            if (!$mostrar_ids && ($k === 'id' || $k === 'persona_id')) {
                continue;
            }

            if (in_array(strtolower(trim($k)), $tinyint_fields)) {
                $v = ($v == 1) ? 'Sí' : 'No';
            }

            $k = ucfirst(str_replace('_', ' ', $k));

            $html .= "
            <tr>
                <td style='border:1px solid #ccc; padding:8px; background:#f5f5f5;'>
                    <strong>$k</strong>
                </td>
                <td style='border:1px solid #ccc; padding:8px;'>
                    $v
                </td>
            </tr>";
        }
    }

    $html .= '</table></div></div>';

    return $html;
}

add_shortcode('buscador_persona', function() {
    global $wpdb;

    $html = '';

    $busqueda = isset($_GET['buscar']) ? sanitize_text_field($_GET['buscar']) : '';
    $persona_id = isset($_GET['persona_id']) ? intval($_GET['persona_id']) : 0;
    $profesion = isset($_GET['profesion']) ? $_GET['profesion'] : [];
    $paged = isset($_GET['pagina']) ? max(1, intval($_GET['pagina'])) : 1;
    $por_pagina = 25;
    $offset = ($paged - 1) * $por_pagina;

    //OBTENER PROFESIONES
    $profesiones = $wpdb->get_col("
        SELECT DISTINCT profesion_oficio 
        FROM laboral_base 
        WHERE profesion_oficio IS NOT NULL
    ");


    //FORMULARIO BUSCADOR
    $html .= '
    <form method="GET" style="margin-bottom:20px;">
        <h3>🔎 Buscar persona</h3>
        <input type="text" name="buscar" placeholder="Nombre o ID" value="'.$busqueda.'">
        <button type="submit">Buscar</button>
    </form>
    ';

    //FORMULARIO FILTRO
    $html .= '
    <form method="GET" style="margin-bottom:20px;">
        <h3>🏷️ Filtrar por profesión</h3>
    ';

    foreach ($profesiones as $p) {

        $checked = (is_array($profesion) && in_array($p, $profesion)) ? 'checked' : '';

        $html .= "
        <label style='display:block; margin-bottom:5px; cursor:pointer;'>
            <input type='checkbox' name='profesion[]' value='$p' $checked style='margin-right:8px;'>
            $p
        </label>
        ";
    }

    $html .= '
        <button type="submit">Filtrar</button>
    </form>
    ';
        if (empty($busqueda) && empty($profesion) && empty($persona_id)) {

        $total = $wpdb->get_var("SELECT COUNT(*) FROM Personal");

        //obtener datos paginados
        $resultados = $wpdb->get_results("
            SELECT 
                p.id, 
                p.nombre, 
                p.telefono,
                lb.profesion_oficio
            FROM Personal p
            LEFT JOIN laboral_base lb ON p.id = lb.persona_id
            LIMIT $por_pagina OFFSET $offset
        ");

        if ($resultados) {

            $html .= "<h3 style='width:800px;margin:20px auto;'>Listado de personal:</h3>";
            $html .= "<table style='width:800px; margin:0 auto; border-collapse:collapse;'>";

            //encabezados
            $html .= "
            <tr>
                <th style='border:1px solid #ccc; padding:8px;'>ID</th>
                <th style='border:1px solid #ccc; padding:8px;'>Nombre</th>
                <th style='border:1px solid #ccc; padding:8px;'>Profesión</th>
                <th style='border:1px solid #ccc; padding:8px;'>Teléfono</th>
                <th style='border:1px solid #ccc; padding:8px;'>Acción</th>
            </tr>
            ";

            foreach ($resultados as $row) {

                $prof = $row->profesion_oficio ?: '—';

                $html .= "
                <tr>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->id</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->nombre</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$prof</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->telefono</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>
                        <a href='?persona_id=$row->id'>
                            <button>Ver Tarjeta CEROPOE</button>
                        </a>
                    </td>
                </tr>";
            }

            $html .= "</table>";

            //PAGINACIÓN
            $total_paginas = ceil($total / $por_pagina);

            $html .= "<div style='text-align:center; margin-top:20px;'>";

            for ($i = 1; $i <= $total_paginas; $i++) {
                $html .= "<a href='?pagina=$i' style='margin:5px;'>$i</a>";
            }

            $html .= "</div>";
        }
    }

    //BUSCADOR (ID / NOMBRE)
    if (!empty($busqueda)) {
        
        if (is_numeric($busqueda)) {
            $resultados = $wpdb->get_results("
                SELECT 
                    p.id, 
                    p.nombre, 
                    p.telefono,
                    lb.profesion_oficio
                FROM Personal p
                LEFT JOIN laboral_base lb ON p.id = lb.persona_id
                WHERE p.id = $busqueda
            ");
        } else {
            $resultados = $wpdb->get_results("
                SELECT 
                    p.id, 
                    p.nombre, 
                    p.telefono,
                    lb.profesion_oficio
                FROM Personal p
                LEFT JOIN laboral_base lb ON p.id = lb.persona_id
                WHERE p.nombre LIKE '%$busqueda%'
            ");
        }

        //auto abrir si es ID único
        if (is_numeric($busqueda) && count($resultados) === 1) {
            $persona_id = $resultados[0]->id;
        }

        //mostrar lista solo si no es ID directo
        if ($resultados && !(is_numeric($busqueda) && count($resultados) === 1)) {

            $html .= "<h3 style='width:800px;margin:20px auto;'>Resultados:</h3>";
            $html .= "<table style='width:800px; margin:0 auto; border-collapse:collapse;'>";

            //encabezados
            $html .= "
            <tr>
                <th style='border:1px solid #ccc; padding:8px;'>ID</th>
                <th style='border:1px solid #ccc; padding:8px;'>Nombre</th>
                <th style='border:1px solid #ccc; padding:8px;'>Profesión</th>
                <th style='border:1px solid #ccc; padding:8px;'>Teléfono</th>
                <th style='border:1px solid #ccc; padding:8px;'>Acción</th>
            </tr>
            ";
            
            foreach ($resultados as $row) {

                $profesion = $row->profesion_oficio ?: '—';

                $html .= "
                <tr>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->id</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->nombre</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$profesion</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->telefono</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>
                        <a href='?persona_id=$row->id'>
                            <button>Ver Tarjeta CEROPOE</button>
                        </a>
                    </td>
                </tr>";
            }

            $html .= "</table>";

        } elseif (!$resultados) {
            $html .= "<p>No se encontraron resultados.</p>";
        }
    }


    //FILTRO POR PROFESIÓN
    if (!empty($profesion) && empty($busqueda)) {

        //convertir array a string SQL seguro
        $valores = [];

        foreach ($profesion as $p) {
            $valores[] = "'" . esc_sql($p) . "'";
        }

        $lista = implode(",", $valores);

        $resultados = $wpdb->get_results("
            SELECT 
                p.id, 
                p.nombre, 
                p.telefono,
                lb.profesion_oficio
            FROM Personal p
            INNER JOIN laboral_base lb ON p.id = lb.persona_id
            WHERE lb.profesion_oficio IN ($lista)
        ");

        if ($resultados) {

            $html .= "<h3 style='width:800px;margin:20px auto;'>Resultados por profesión:</h3>";
            $html .= "<table style='width:800px; margin:0 auto; border-collapse:collapse;'>";

            //encabezados
            $html .= "
            <tr>
                <th style='border:1px solid #ccc; padding:8px;'>ID</th>
                <th style='border:1px solid #ccc; padding:8px;'>Nombre</th>
                <th style='border:1px solid #ccc; padding:8px;'>Profesión</th>
                <th style='border:1px solid #ccc; padding:8px;'>Teléfono</th>
                <th style='border:1px solid #ccc; padding:8px;'>Acción</th>
            </tr>
            ";

            foreach ($resultados as $row) {

                $profesion = $row->profesion_oficio ?: '—';

                $html .= "
                <tr>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->id</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->nombre</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$profesion</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>$row->telefono</td>
                    <td style='padding:10px; border:1px solid #ccc; text-align:center;'>
                        <a href='?persona_id=$row->id'>
                            <button>Ver Tarjeta CEROPOE</button>
                        </a>
                    </td>
                </tr>";
            }

            $html .= "</table>";

        } else {
            $html .= "<p>No hay resultados para esta profesión.</p>";
        }
    }


    //EXPEDIENTE
    if ($persona_id > 0) {

        // PERSONAL
        $html .= render_tabla("PERSONAL", "Personal",
            $wpdb->get_results("SELECT * FROM Personal WHERE id = $persona_id", ARRAY_A),
            true
        );

        // SALUD
        $html .= render_tabla("SALUD", "salud",
            $wpdb->get_results("SELECT * FROM salud WHERE persona_id = $persona_id", ARRAY_A)
        );

        // CONTACTO EMERGENCIA
        $html .= render_tabla("CONTACTO EMERGENCIA", "contacto_emergencia",
            $wpdb->get_results("SELECT * FROM contacto_emergencia WHERE persona_id = $persona_id", ARRAY_A)
        );

        // ANTECEDENTES PENALES
        $html .= render_tabla("ANTECEDENTES PENALES", "antecedentes_penales",
            $wpdb->get_results("SELECT * FROM antecedentes_penales WHERE persona_id = $persona_id", ARRAY_A)
        );

        // ANTIDOPING
        $html .= render_tabla("ANTIDOPING", "antidoping",
            $wpdb->get_results("SELECT * FROM antidoping WHERE persona_id = $persona_id", ARRAY_A)
        );

        // ESTRES
        $html .= render_tabla("ESTRÉS", "estres",
            $wpdb->get_results("SELECT * FROM estres WHERE persona_id = $persona_id", ARRAY_A)
        );

        // EDUCACION
        $html .= render_tabla("EDUCACIÓN", "educacion",
            $wpdb->get_results("SELECT * FROM educacion WHERE persona_id = $persona_id", ARRAY_A)
        );

        // LABORAL BASE
        $html .= render_tabla("LABORAL BASE", "laboral_base",
            $wpdb->get_results("SELECT * FROM laboral_base WHERE persona_id = $persona_id", ARRAY_A)
        );

        // HISTORIAL LABORAL
        $html .= render_tabla("HISTORIAL LABORAL", "historial_laboral",
            $wpdb->get_results("SELECT * FROM historial_laboral WHERE persona_id = $persona_id", ARRAY_A)
        );

        // ACCIDENTES LABORALES
        $html .= render_tabla("ACCIDENTES LABORALES", "accidentes_laborales",
            $wpdb->get_results("SELECT * FROM accidentes_laborales WHERE persona_id = $persona_id", ARRAY_A)
        );

        // CURSOS SEGURIDAD
        $html .= render_tabla("CURSOS SEGURIDAD", "cursos_seguridad",
            $wpdb->get_results("SELECT * FROM cursos_seguridad WHERE persona_id = $persona_id", ARRAY_A)
        );

        // CURSO TECNICO ULTIMO
        $html .= render_tabla("CURSO TÉCNICO ÚLTIMO", "curso_tecnico_ultimo",
            $wpdb->get_results("SELECT * FROM curso_tecnico_ultimo WHERE persona_id = $persona_id", ARRAY_A)
        );

        // CURSO SEGURIDAD ULTIMO
        $html .= render_tabla("CURSO SEGURIDAD ÚLTIMO", "curso_seguridad_ultimo",
            $wpdb->get_results("SELECT * FROM curso_seguridad_ultimo WHERE persona_id = $persona_id", ARRAY_A)
        );

        // EVALUACIONES SEGURIDAD
        $html .= render_tabla("EVALUACIONES SEGURIDAD", "evaluaciones_seguridad",
            $wpdb->get_results("SELECT * FROM evaluaciones_seguridad WHERE persona_id = $persona_id", ARRAY_A)
        );
    }

    return $html;
});

add_action('wp_footer', function() {
    ?>
    <script>
    function toggleSeccion(id) {
        var el = document.getElementById(id);
        if (el.style.display === "none") {
            el.style.display = "block";
        } else {
            el.style.display = "none";
        }
    }
    </script>
    <?php
});


register_activation_hook(__FILE__, function() {

    $paginas = [
        [
            'titulo' => 'Sistema',
            'slug' => 'sistema',
            'shortcode' => '[buscador_persona]'
        ],
        [
            'titulo' => 'Reportes',
            'slug' => 'reportes',
            'shortcode' => '[reportes]'
        ]
    ];

    foreach ($paginas as $p) {

        $pagina_existente = get_page_by_path($p['slug']);

        if (!$pagina_existente) {

            $page_id = wp_insert_post([
                'post_title'   => $p['titulo'],
                'post_name'    => $p['slug'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_content' => $p['shortcode']
            ]);

            // Si quieres que "Sistema" sea página principal
            if ($p['slug'] === 'sistema') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $page_id);
            }
        }
    }

    // Refrescar enlaces permanentes
    flush_rewrite_rules();

});
