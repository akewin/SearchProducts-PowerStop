// todos os tipos de produtos existentes
function get_product_types() {
    $tipos = [];
    
    $args = [
        'post_type' => 'produtos',
        'posts_per_page' => -1,
    ];
    
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $tipo = get_field('tipo');
            if (!empty($tipo)) {
                $tipos[$tipo] = ucfirst($tipo);
            }
        }
        wp_reset_postdata();
    }
    
    return $tipos;
}

// obter dados da tabela ACF
function get_products_data() {
    $marcas = [];
    $contagem = [];

    $args = [
        'post_type' => 'produtos',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $tabela = get_field('tabela');
            $tipo = get_field('tipo');

            if (empty($tabela) || !is_array($tabela) || !isset($tabela['body'])) {
                continue;
            }

            foreach ($tabela['body'] as $linha) {
                $marca = ucfirst(strtolower(trim($linha[0]['c'] ?? '')));
                $modelo = trim($linha[1]['c'] ?? '');
                $ano = trim($linha[2]['c'] ?? '');

                if (empty($marca) || strtolower($marca) === 'montadora') {
                    continue;
                }

                if ($marca && $modelo) {
                    $contagem[$marca] = ($contagem[$marca] ?? 0) + 1;
                    $contagem[$marca . '_' . $modelo] = ($contagem[$marca . '_' . $modelo] ?? 0) + 1;
                    $contagem[$marca . '_' . $modelo . '_' . $ano] = ($contagem[$marca . '_' . $modelo . '_' . $ano] ?? 0) + 1;

                    $marcas[$marca][$modelo][] = $ano;
                }
            }
        }
        wp_reset_postdata();
    }

    ksort($marcas);
    foreach ($marcas as $marca => $modelos) {
        ksort($marcas[$marca]);
        foreach ($modelos as $modelo => $anos) {
            sort($marcas[$marca][$modelo]);
        }
    }

    return ['marcas' => $marcas, 'contagem' => $contagem];
}

// resultados
function get_product_results($tipos = [], $marca = 'todos', $modelo = 'todos', $ano = 'todos', $codigo = '', $numero_ate = '', $numero_bosch = '', $numero_varga = '', $numero_controil = '', $numero_fluidloc = '') {
    $args = [
        'post_type' => 'produtos',
        'posts_per_page' => -1,
    ];

    $meta_query = [];

     if (!empty($codigo)) {
        $codigo_query = ['relation' => 'OR'];
        $codigo_query[] = [
            'key' => 'codigo',
            'value' => $codigo,
            'compare' => 'LIKE'
        ];
        $codigo_query[] = [
            'key' => 'modelos_copiar',
            'value' => $codigo,
            'compare' => 'LIKE'
        ];
        $meta_query[] = $codigo_query;
    }

    if (!empty($numero_ate)) {
        $meta_query[] = [
            'key' => 'numero_ate',
            'value' => $numero_ate,
            'compare' => 'LIKE'
        ];
    }

    if (!empty($numero_bosch)) {
        $meta_query[] = [
            'key' => 'numero_bosch',
            'value' => $numero_bosch,
            'compare' => 'LIKE'
        ];
    }

    if (!empty($numero_varga)) {
        $meta_query[] = [
            'key' => 'numero_varga',
            'value' => $numero_varga,
            'compare' => 'LIKE'
        ];
    }

    if (!empty($numero_controil)) {
        $meta_query[] = [
            'key' => 'numero_controil',
            'value' => $numero_controil,
            'compare' => 'LIKE'
        ];
    }

    if (!empty($numero_fluidloc)) {
        $meta_query[] = [
            'key' => 'numero_fluidloc',
            'value' => $numero_fluidloc,
            'compare' => 'LIKE'
        ];
    }
	
    // Adiciona filtro por tipo se algum tipo for selecionado
    if (!empty($tipos)) {
        $tipo_query = ['relation' => 'OR'];
        foreach ($tipos as $tipo) {
            $tipo_query[] = [
                'key' => 'tipo',
                'value' => $tipo,
                'compare' => '='
            ];
        }
        $meta_query[] = $tipo_query;
    }

    // Adiciona outros filtros apenas se não houver código ou se houver código e outros filtros
    if (empty($codigo) || (!empty($codigo) && ($marca !== 'todos' || $modelo !== 'todos' || $ano !== 'todos'))) {
        if ($marca !== 'todos') {
            $meta_query[] = ['key' => 'tabela', 'value' => $marca, 'compare' => 'LIKE'];
        }

        if ($modelo !== 'todos') {
            $meta_query[] = ['key' => 'tabela', 'value' => $modelo, 'compare' => 'LIKE'];
        }

        if ($ano !== 'todos') {
            $meta_query[] = ['key' => 'tabela', 'value' => $ano, 'compare' => 'LIKE'];
        }
    }

    if (!empty($meta_query)) {
        if (count($meta_query) > 1) {
            $args['meta_query'] = [
                'relation' => 'AND',
                ...$meta_query
            ];
        } else {
            $args['meta_query'] = $meta_query;
        }
    }

    return new WP_Query($args);
}


//shortcode
function produtos_filtro_shortcode() {
    ob_start();
    
    $data = get_products_data();
    $marcas = $data['marcas'];
    $contagem = $data['contagem'];
    $tipos_produtos = get_product_types();
    ?>
    <style>
    .filters-container {
        margin: 20px 0;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    }
    
    .filters-label {
        font-weight: 500;
        color: #333;
        margin-right: 15px;
        display: inline-block;
    }

    .filters-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        align-items: flex-start;
        margin-bottom: 20px;
    }

    .filter-group {
        position: relative;
        min-width: 200px;
    }

    .filter-group select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        background-color: white;
        font-size: 16px;
        color: #333;
        cursor: pointer;
    }

    .custom-select-container {
        position: relative;
        width: 100%;
    }

    .custom-select-search {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
        color: #333;
        background-color: white;
    }

    .custom-select-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        width: 100%;
        max-height: 300px;
        overflow-y: auto;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0 0 4px 4px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        z-index: 1000;
        display: none;
    }

    .custom-select-option {
        padding: 8px 12px;
        cursor: pointer;
        transition: background-color 0.2s;
    }

    .custom-select-option:hover {
        background-color: #f5f5f5;
    }

    .custom-select-option.selected {
        background-color: #e6f3ff;
    }

    .filter-button {
        background-color: #333;
        color: white;
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 16px;
        transition: background-color 0.3s;
    }
		
	.product-grid .product-item h4 a {
		color: #333;  /* Cor cinza escuro - você pode mudar para a cor que preferir */
		text-decoration: none;
		transition: color 0.3s ease;
	}

	.product-grid .product-item h4 a:hover {
		color: #D7AF0E;  /* Cor quando passar o mouse */
	}

    .filter-button:hover {
        background-color: #444;
    }

    .tipos-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border-radius: 4px;
    }

    .tipo-checkbox {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .tipo-checkbox input[type="checkbox"] {
        margin: 0;
    }

    .tipo-checkbox label {
        font-size: 16px;
        color: #333;
        cursor: pointer;
    }
		
	.search-sections {
        margin-bottom: 30px;
    }
    
    .search-section {
        background: #f9f9f9;
        padding: 20px;
        border-radius: 4px;
        margin-bottom: 20px;
    }
    
    .search-section-title {
        font-size: 18px;
        font-weight: 500;
        margin-bottom: 15px;
        color: #333;
    }
    
    .codigo-search {
        width: 100%;
        max-width: 300px;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
        color: #333;
    }
	   .number-search-container {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 15px;
    }
    
    .number-search-field {
        flex: 1;
        min-width: 200px;
    }
    
    .number-search-input {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 16px;
        color: #333;
    }

    </style>

    <div class="filters-container">
        <div class="search-sections">
    <div class="search-section">
        <h3 class="search-section-title">Busque por código PowerStop/modelo do veículo:</h3>
        <input type="text" 
               id="codigo-search" 
               class="codigo-search" 
               placeholder="Digite o código PowerStop ou modelo..."
               pattern="[0-9A-Za-z]+"
               title="Digite apenas letras e números">
    </div>
	
			
	<div class="filters-container">
        <div class="search-sections">
            <div class="search-section">
                <h3 class="search-section-title">Busque por número original:</h3>
                <div class="number-search-container">
                    <div class="number-search-field">
                        <input type="text" 
                               id="numero-ate-search" 
                               class="number-search-input" 
                               placeholder="Número ATE">
                    </div>
                    <div class="number-search-field">
                        <input type="text" 
                               id="numero-bosch-search" 
                               class="number-search-input" 
                               placeholder="Número Bosch">
                    </div>
                    <div class="number-search-field">
                        <input type="text" 
                               id="numero-varga-search" 
                               class="number-search-input" 
                               placeholder="Número Varga">
                    </div>
                    <div class="number-search-field">
                        <input type="text" 
                               id="numero-controil-search" 
                               class="number-search-input" 
                               placeholder="Número Controil">
                    </div>
                    <div class="number-search-field">
                        <input type="text" 
                               id="numero-fluidloc-search" 
                               class="number-search-input" 
                               placeholder="Número Fluidloc">
                    </div>
                </div>
            </div>
    
    <div class="search-section">
        <h3 class="search-section-title">Busque por atributos:</h3>
        <div class="tipos-container">
            <span class="filters-label">Tipos:</span>
            <?php foreach ($tipos_produtos as $valor => $label): ?>
                <div class="tipo-checkbox">
                    <input type="checkbox" id="tipo-<?php echo esc_attr($valor); ?>" name="tipos[]" value="<?php echo esc_attr($valor); ?>">
                    <label for="tipo-<?php echo esc_attr($valor); ?>"><?php echo esc_html($label); ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="filters-row">
            <div class="filter-group">
                <div class="custom-select-container">
                    <input type="text" 
                           class="custom-select-search" 
                           id="marca-search" 
                           placeholder="Buscar marca..." 
                           autocomplete="off">
                    <div class="custom-select-dropdown" id="marca-dropdown">
                        <div class="custom-select-option" data-value="todos">Todas as marcas</div>
                        <?php foreach (array_keys($marcas) as $marca): ?>
                            <div class="custom-select-option" data-value="<?php echo esc_attr($marca); ?>">
                                <?php echo esc_html($marca); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <div class="custom-select-container">
                    <input type="text" 
                           class="custom-select-search" 
                           id="modelo-search" 
                           placeholder="Buscar modelo..." 
                           autocomplete="off">
                    <div class="custom-select-dropdown" id="modelo-dropdown">
                        <div class="custom-select-option" data-value="todos">Todos os modelos</div>
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <select name="ano" id="filter-ano">
                    <option value="todos">Ano</option>
                </select>
            </div>

            <button class="filter-button" id="filtrar-btn">FILTRAR</button>
			    <a href="#" id="reset-filters" style="margin-left: 10px; color: #666; text-decoration: underline; cursor: pointer;">Redefinir filtros</a>
        </div>
    </div>

    <div id="produtos-resultados" class="product-results">
        <!-- Resultados serão carregados aqui via AJAX -->
    </div>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		var produtos = <?php echo json_encode($marcas); ?>;
		var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
		var selectedMarca = 'todos';
		var selectedModelo = 'todos';

		// Função para filtrar opções do dropdown
		function filterDropdownOptions(searchInput, dropdownId) {
			var searchText = searchInput.toLowerCase();
			$('#' + dropdownId + ' .custom-select-option').each(function() {
				var optionText = $(this).text().toLowerCase();
				$(this).toggle(optionText.includes(searchText));
			});
		}

		// Configuração dos dropdowns de marca e modelo
		$('.custom-select-search').on('focus click', function(e) {
			e.stopPropagation();
			$('.custom-select-dropdown').not($(this).siblings('.custom-select-dropdown')).hide();
			$(this).siblings('.custom-select-dropdown').show();
		}).on('input', function() {
			var dropdownId = $(this).siblings('.custom-select-dropdown').attr('id');
			filterDropdownOptions($(this).val(), dropdownId);
		});

		// Seleção de marca
		$('#marca-dropdown').on('click', '.custom-select-option', function() {
			selectedMarca = $(this).data('value');
			$('#marca-search').val($(this).text().trim());
			$('#marca-dropdown').hide();

			selectedModelo = 'todos';
			$('#modelo-search').val('');

			updateModeloOptions();
		});

		// Seleção de modelo
		$('#modelo-dropdown').on('click', '.custom-select-option', function() {
			selectedModelo = $(this).data('value');
			$('#modelo-search').val($(this).text().trim());
			$('#modelo-dropdown').hide();

			updateAnoOptions();
		});

		// Atualiza opções de modelo baseado na marca selecionada
		function updateModeloOptions() {
			var $modeloDropdown = $('#modelo-dropdown');
			$modeloDropdown.empty().append(
				'<div class="custom-select-option" data-value="todos">Todos os modelos</div>'
			);

			if (selectedMarca !== 'todos' && produtos[selectedMarca]) {
				Object.keys(produtos[selectedMarca]).forEach(function(modelo) {
					$modeloDropdown.append(
						'<div class="custom-select-option" data-value="' + modelo + '">' + 
						modelo + '</div>'
					);
				});
			}
		}

		// Atualiza opções de ano baseado na marca e modelo selecionados
		function updateAnoOptions() {
			var $anoSelect = $('#filter-ano');
			$anoSelect.empty().append('<option value="todos">Ano</option>');

			if (selectedMarca !== 'todos' && selectedModelo !== 'todos' && 
				produtos[selectedMarca] && produtos[selectedMarca][selectedModelo]) {
				produtos[selectedMarca][selectedModelo].forEach(function(ano) {
					$anoSelect.append('<option value="' + ano + '">' + ano + '</option>');
				});
			}
		}

		// Fecha dropdowns ao clicar fora
		$(document).click(function() {
			$('.custom-select-dropdown').hide();
		});

		$('.custom-select-dropdown').click(function(e) {
			e.stopPropagation();
		});

		var codigoTimeout;
        $('#codigo-search, #numero-ate-search, #numero-bosch-search, #numero-varga-search, #numero-controil-search, #numero-fluidloc-search').on('input', function() {
            clearTimeout(codigoTimeout);
            codigoTimeout = setTimeout(carregarResultados, 500);
        });

		function carregarResultados() {
            var tipos = [];
            $('input[name="tipos[]"]:checked').each(function() {
                tipos.push($(this).val());
            });

            var ano = $('#filter-ano').val();
            var codigo = $('#codigo-search').val();
            var numero_ate = $('#numero-ate-search').val();
            var numero_bosch = $('#numero-bosch-search').val();
            var numero_varga = $('#numero-varga-search').val();
            var numero_controil = $('#numero-controil-search').val();
            var numero_fluidloc = $('#numero-fluidloc-search').val();

            $('#produtos-resultados').html('<p>Carregando...</p>');

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'filtrar_produtos',
                    tipos: tipos,
                    marca: selectedMarca,
                    modelo: selectedModelo,
                    ano: ano,
                    codigo: codigo || '',
                    numero_ate: numero_ate || '',
                    numero_bosch: numero_bosch || '',
                    numero_varga: numero_varga || '',
                    numero_controil: numero_controil || '',
                    numero_fluidloc: numero_fluidloc || ''
                },
                success: function(response) {
                    $('#produtos-resultados').html(response);
                }
            });
        }

		// Botão Filtrar
		$('#filtrar-btn').click(carregarResultados);
		
		// Botão de reset
		$('#reset-filters').click(function(e) {
		e.preventDefault();

		// Limpa os checkboxes de tipos
        $('input[name="tipos[]"]').prop('checked', false);

        // Reset marca
        selectedMarca = 'todos';
        $('#marca-search').val('');

        // Reset modelo
        selectedModelo = 'todos';
        $('#modelo-search').val('');

        // Reset ano
        $('#filter-ano').val('todos');

        // Limpa código e números
        $('#codigo-search').val('');
        $('#numero-ate-search').val('');
        $('#numero-bosch-search').val('');
        $('#numero-varga-search').val('');
        $('#numero-controil-search').val('');
        $('#numero-fluidloc-search').val('');
		// Atualiza os dropdowns
		updateModeloOptions();
		updateAnoOptions();

    // Carrega resultados
    carregarResultados();
});

		// Carrega resultados iniciais
		carregarResultados();
	});
	</script>
    <?php
    return ob_get_clean();
}

// Handler AJAX para filtrar produtos
function filtrar_produtos_ajax() {
    $tipos = isset($_POST['tipos']) ? array_map('sanitize_text_field', $_POST['tipos']) : [];
    $marca = isset($_POST['marca']) ? sanitize_text_field($_POST['marca']) : 'todos';
    $modelo = isset($_POST['modelo']) ? sanitize_text_field($_POST['modelo']) : 'todos';
    $ano = isset($_POST['ano']) ? sanitize_text_field($_POST['ano']) : 'todos';
    $codigo = isset($_POST['codigo']) ? sanitize_text_field($_POST['codigo']) : '';
    $numero_ate = isset($_POST['numero_ate']) ? sanitize_text_field($_POST['numero_ate']) : '';
    $numero_bosch = isset($_POST['numero_bosch']) ? sanitize_text_field($_POST['numero_bosch']) : '';
    $numero_varga = isset($_POST['numero_varga']) ? sanitize_text_field($_POST['numero_varga']) : '';
    $numero_controil = isset($_POST['numero_controil']) ? sanitize_text_field($_POST['numero_controil']) : '';
    $numero_fluidloc = isset($_POST['numero_fluidloc']) ? sanitize_text_field($_POST['numero_fluidloc']) : '';

    $query = get_product_results($tipos, $marca, $modelo, $ano, $codigo, $numero_ate, $numero_bosch, $numero_varga, $numero_controil, $numero_fluidloc);

    ob_start();
    
    if ($query->have_posts()): ?>
        <div class="product-grid" style="display: flex; flex-wrap: wrap;">
            <?php while ($query->have_posts()): $query->the_post(); ?>
                <div class="product-item" style="border: 1px solid #ccc; margin: 10px; padding: 10px; width: calc(33% - 20px); box-sizing: border-box;">
                    <a href="<?php echo esc_url(get_permalink()); ?>">
                        <?php echo get_the_post_thumbnail(get_the_ID(), 'medium'); ?>
                    </a>
                    <h4>
                        <a href="<?php echo esc_url(get_permalink()); ?>">
                            <?php echo esc_html(get_the_title()); ?>
                        </a>
                    </h4>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <p>Nenhum produto encontrado.</p>
    <?php endif;

    wp_reset_postdata();
    
    $html = ob_get_clean();
    echo $html;
    wp_die();
}

// Registra a ação AJAX
add_action('wp_ajax_filtrar_produtos', 'filtrar_produtos_ajax');
add_action('wp_ajax_nopriv_filtrar_produtos', 'filtrar_produtos_ajax');

// Registra o shortcode
add_shortcode('produtos_filtro', 'produtos_filtro_shortcode');



function produtos_filtro_redirect_shortcode() {
    ob_start();
    
    $data = get_products_data();
    $marcas = $data['marcas'];
    $tipos_produtos = get_product_types();
    ?>
    <style>
    .filters-container {
        margin: 20px auto;
        max-width: 1200px;
        padding: 30px;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        position: relative;
    }

    .clickable-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 9999;
        cursor: pointer;
        display: block;
        background: transparent;
    }

    .search-sections {
        pointer-events: none;
    }

    .search-section {
        margin-bottom: 30px;
    }

    .search-section-title {
        font-size: 1.2em;
        color: #2c3e50;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .codigo-search {
        width: 100%;
        max-width: 400px;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        color: #333;
        background-color: #f8f9fa;
    }

    .tipos-container {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-bottom: 20px;
        padding: 15px;
        background-color: #f8f9fa;
        border-radius: 5px;
        align-items: center;
    }

    .filters-label {
        font-weight: 600;
        color: #2c3e50;
        margin-right: 15px;
        font-size: 1.1em;
    }

    .tipo-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .filters-row {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
        align-items: flex-start;
        margin-top: 20px;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .custom-select-container {
        position: relative;
    }

    .custom-select-search {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        color: #333;
        background-color: #f8f9fa;
    }

    .custom-select-dropdown {
        display: none;
    }

    #filter-ano {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 16px;
        color: #333;
        background-color: #f8f9fa;
        cursor: not-allowed;
    }

    .filter-button {
        padding: 10px 30px;
        background-color: #2c3e50;
        color: white;
        border: none;
        border-radius: 5px;
        font-weight: 500;
        text-transform: uppercase;
        font-size: 16px;
        cursor: not-allowed;
        opacity: 0.9;
    }
    </style>

    <div class="filters-container">
        <!-- Overlay clicável que redireciona para /produtos -->
        <a href="/produtos" class="clickable-overlay"></a>

        <div class="search-sections">
            
            
            <div class="search-section">
                <h3 class="search-section-title">Busque por atributos:</h3>
                <div class="tipos-container">
                    <span class="filters-label">Tipos:</span>
                    <?php foreach ($tipos_produtos as $valor => $label): ?>
                        <div class="tipo-checkbox">
                            <input type="checkbox" 
                                   id="tipo-<?php echo esc_attr($valor); ?>" 
                                   name="tipos[]" 
                                   value="<?php echo esc_attr($valor); ?>"
                                   disabled>
                            <label for="tipo-<?php echo esc_attr($valor); ?>"><?php echo esc_html($label); ?></label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="filters-row">
                    <div class="filter-group">
                        <div class="custom-select-container">
                            <input type="text" 
                                   class="custom-select-search" 
                                   id="marca-search" 
                                   placeholder="Buscar marca..." 
                                   readonly>
                        </div>
                    </div>
                    <div class="filter-group">
                        <div class="custom-select-container">
                            <input type="text" 
                                   class="custom-select-search" 
                                   id="modelo-search" 
                                   placeholder="Buscar modelo..." 
                                   readonly>
                        </div>
                    </div>
                    <div class="filter-group">
                        <select name="ano" id="filter-ano" disabled>
                            <option value="todos">Ano</option>
                        </select>
                    </div>
                    <button class="filter-button" disabled>FILTRAR</button>
                </div>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('produtos_filtro_redirect', 'produtos_filtro_redirect_shortcode');
