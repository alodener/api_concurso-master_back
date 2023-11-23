<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeopleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $firstNames = [
            'Helena', 'Alice', 'Laura', 'Maria Alice', 'Sophia', 'Manuela', 'Maitê', 'Liz', 'Cecília', 'Isabella',
            'Luísa', 'Eloá', 'Heloísa', 'Júlia', 'Ayla', 'Maria Luísa', 'Isis', 'Elisa', 'Antonella', 'Valentina',
            'Maya', 'Maria Júlia', 'Aurora', 'Lara', 'Maria Clara', 'Lívia', 'Esther', 'Giovanna', 'Sarah',
            'Maria Cecília', 'Lorena', 'Beatriz', 'Rebeca', 'Luna', 'Olívia', 'Maria Helena', 'Mariana', 'Isadora',
            'Melissa', 'Maria', 'Catarina', 'Lavínia', 'Alícia', 'Maria Eduarda', 'Agatha', 'Ana Liz', 'Yasmin',
            'Emanuelly', 'Ana Clara', 'Clara', 'João', 'Lucas', 'Pedro', 'Mateus', 'Davi', 'Arthur', 'Bernardo',
            'Heitor', 'Rafael', 'Miguel', 'Enzo', 'Ethan', 'Gabriel', 'Lucca', 'Benjamin', 'Nicolas', 'Guilherme',
            'Gustavo', 'Murilo', 'Felipe', 'Samuel', 'Henrique', 'Lorenzo', 'Vinicius', 'Joaquim', 'Leonardo', 'Ryan',
            'Ian', 'Antônio', 'Victor', 'Bruno', 'Carlos', 'Davi Lucas', 'Kaique', 'Patrick', 'Igor', 'Diego', 'Alexandre',
            'Mateus Henrique', 'Gustavo Henrique', 'Enzo Gabriel', 'Luiz Miguel', 'Felipe', 'Lucas Gabriel', 'Pedro Henrique',
            'Leonardo', 'Vinicius', 'Vicente', 'Eduardo', 'Fillipi'
        ];

        $lastNames = [
            'da Silva', 'dos Santos', 'Pereira', 'Alves', 'Ferreira', 'de Oliveira', 'Silva', 'Rodrigues', 'de Souza', 'Gomes',
            'Santos', 'Oliveira', 'Ribeiro', 'Martins', 'Gonçalves', 'Soares', 'Barbosa', 'Lopes', 'Vieira', 'Souza', 'Fernandes',
            'Lima', 'Costa', 'Batista', 'Dias', 'Moreira', 'de Lima', 'de Sousa', 'Nunes', 'da Costa', 'de Almeida', 'Mendes',
            'Carvalho', 'Araujo', 'Cardoso', 'Teixeira', 'Marques', 'do Nascimento', 'Almeida', 'Ramos', 'Machado', 'Rocha',
            'Nascimento', 'de Araujo', 'da Conceiçao', 'Bezerra', 'Sousa', 'Borges', 'Santana', 'de Carvalho', 'Aparecido',
            'Pinto', 'Pinheiro', 'Monteiro', 'Andrade', 'Leite', 'Correa', 'Nogueira', 'Garcia', 'de Freitas', 'Henrique',
            'Tavares', 'Coelho', 'Pires', 'de Paula', 'Correia', 'Miranda', 'de Jesus', 'Duarte', 'Freitas', 'Barros', 'de Andrade',
            'Campos', 'Sántos', 'de Melo', 'da Cruz', 'Reis', 'Guimaraes', 'Moraes', 'do Carmo', 'dos Reis', 'Viana', 'de Castro',
            'Silveira', 'Moura', 'Brito', 'Neves', 'Carneiro', 'Melo', 'Medeiros', 'Cordeiro', 'Conceição', 'Farias', 'Dantas',
            'Cavalcante', 'da Rocha', 'de Assis', 'Braga', 'Cruz', 'Siqueira'
        ];

        foreach ($firstNames as $firstName) {
            $lastName = array_shift($lastNames);
            DB::table('people')->insert([
                'first_name' => $firstName,
                'last_name' => $lastName,
            ]);
        }
    }
}
