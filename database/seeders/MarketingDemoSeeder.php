<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tipos;
use App\Models\Midia;
use App\Models\Modelo;
use App\Models\Carro;

class MarketingDemoSeeder extends Seeder
{
    public function run(): void
    {
        /* ================= TIPOS ================= */
        $tiposBase = ['Hatch','SUV','Sedan','MiniVan','Outro'];
        foreach ($tiposBase as $nome) {
            Tipos::updateOrCreate(['nome' => $nome], ['ativo' => true]);
        }

        /* ================= MÍDIAS ================= */
        $midias = [
            ['nome' => 'Meta Ads',  'slug' => 'meta-ads'],   // normaliza IG/FB
            ['nome' => 'Instagram', 'slug' => 'instagram'],  // se quiser manter separado
            ['nome' => 'Facebook',  'slug' => 'facebook'],   // idem
            ['nome' => 'Google',    'slug' => 'google'],
            ['nome' => 'Linkedin',  'slug' => 'linkedin'],
            ['nome' => 'X',         'slug' => 'x'],
            ['nome' => 'Record Tv', 'slug' => 'record-tv'],
            ['nome' => 'Indicação', 'slug' => 'indicacao'],
            ['nome' => 'Outro',     'slug' => 'outro'],
        ];
        foreach ($midias as $m) {
            Midia::updateOrCreate(
                ['slug' => $m['slug']],
                ['nome' => $m['nome'], 'ativo' => true]
            );
        }

        /* ================= MODELOS =================
         * Campo `tipo` é string (Hatch|SUV|Sedan|MiniVan|Outro)
         */
        $modelos = [
            ['nome' => 'Kwid',        'tipo' => 'Hatch'],
            ['nome' => 'C3',          'tipo' => 'Hatch'],
            ['nome' => 'Onix',        'tipo' => 'Hatch'],
            ['nome' => 'Logan',       'tipo' => 'Sedan'],
            ['nome' => 'Fastback',    'tipo' => 'SUV'],
            ['nome' => 'C3 Aircross', 'tipo' => 'SUV'],
            ['nome' => 'Spin',        'tipo' => 'MiniVan'],
            ['nome' => 'Duster',      'tipo' => 'SUV'],
        ];

        $mapModelos = [];
        foreach ($modelos as $m) {
            $modelo = Modelo::updateOrCreate(
                ['nome' => $m['nome']],
                ['tipo' => $m['tipo'], 'ativo' => true]
            );
            $mapModelos[$m['nome']] = $modelo->id;
        }

        /* ================= CARROS =================
         * `tipos` é JSON (array de strings).
         */
        $place = fn(string $text) => 'https://placehold.co/960x540?text=' . urlencode($text);

        $carros = [
            [
                'modelo'     => 'Kwid',
                'titulo'     => 'Renault Kwid',
                'descricao'  => 'Kwid Zen 1.0 Flex',
                'tipos'      => ['Hatch'],
                'imagem_url' => $place('Renault+Kwid'),
            ],
            [
                'modelo'     => 'C3',
                'titulo'     => 'Citroën C3',
                'descricao'  => 'C3 Live 1.0 Flex',
                'tipos'      => ['Hatch'],
                'imagem_url' => $place('Citroen+C3'),
            ],
            [
                'modelo'     => 'Onix',
                'titulo'     => 'Chevrolet Onix',
                'descricao'  => 'Onix MT 1.0 Flex',
                'tipos'      => ['Hatch'],
                'imagem_url' => $place('Chevrolet+Onix'),
            ],
            [
                'modelo'     => 'Logan',
                'titulo'     => 'Renault Logan',
                'descricao'  => 'Logan Life 1.0 Flex',
                'tipos'      => ['Sedan'],
                'imagem_url' => $place('Renault+Logan'),
            ],
            [
                'modelo'     => 'Fastback',
                'titulo'     => 'Fiat Fastback',
                'descricao'  => 'Fastback Turbo 1.0 Flex AT',
                'tipos'      => ['SUV'],
                'imagem_url' => $place('Fiat+Fastback'),
            ],
            [
                'modelo'     => 'C3 Aircross',
                'titulo'     => 'Citroën C3 Aircross',
                'descricao'  => 'C3 Aircross 7L 1.0 Turbo Flex',
                'tipos'      => ['SUV'],
                'imagem_url' => $place('Citroen+C3+Aircross'),
            ],
            [
                'modelo'     => 'Spin',
                'titulo'     => 'Chevrolet Spin',
                'descricao'  => 'Spin LTZ 7L 1.8 Flex AT',
                'tipos'      => ['MiniVan'],
                'imagem_url' => $place('Chevrolet+Spin'),
            ],
            [
                'modelo'     => 'Duster',
                'titulo'     => 'Renault Duster',
                'descricao'  => 'Duster Intense 1.6 Flex CVT',
                'tipos'      => ['SUV'],
                'imagem_url' => $place('Renault+Duster'),
            ],
        ];

        foreach ($carros as $c) {
            $modeloId = $mapModelos[$c['modelo']] ?? null;
            if (!$modeloId) continue;

            Carro::updateOrCreate(
                ['modelo_id' => $modeloId, 'titulo' => $c['titulo']],
                [
                    'descricao'  => $c['descricao'],
                    'imagem_url' => $c['imagem_url'],
                    'tipos'      => $c['tipos'], // JSON
                    'ativo'      => true,
                ]
            );
        }
    }
}
