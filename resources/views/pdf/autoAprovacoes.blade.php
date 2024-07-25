    <!-- resources/views/relatorio.blade.php -->
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Relatório Financeiro</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                background-color: #f2f2f2;
                margin: 0;
                padding: 20px;
            }

            .container {
                max-width: 800px;
                margin: 0 auto;
                background-color: #fff;
                padding: 20px;
                border-radius: 8px;
                box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            }

            h4 {
                color: #333;
                text-align: center;
                margin-bottom: 20px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }

            th, td {
                border: 1px solid #ddd;
                padding: 8px;
                text-align: center;
            }

            th {
                background-color: #f2f2f2;
            }

            tbody tr:nth-child(even) {
                background-color: #f9f9f9;
            }

            div.total {
                display: flex; /* Tornar o contêiner um flex container */
                justify-content: space-between; /* Dividir o espaço entre os itens */
                flex-wrap: wrap; /* Permitir que os itens quebrem para a linha seguinte se necessário */
                text-align: center;
                margin-top: 20px;
            }

            div.total p {
                flex: 0 0 20%; /* Manter o tamanho original dos itens */
                margin: 10px 0; /* Adicionar espaçamento entre os itens */
                padding: 10px; /* Adicionar padding para melhorar a aparência */
                border: 1px solid #ddd; /* Adicionar borda para separar visualmente os itens */
                border-radius: 8px; /* Arredondar as bordas */
                background-color: #f2f2f2; /* Adicionar uma cor de fundo */
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h4>Relatório de Auto Aprovações - {{ $title }}</h4>

            <!-- Tabela -->
            <table>
                <thead>
                    <tr>
                        <th>Banca</th>
                        <th>Pagamento Total</th>
                        <th>Média/dia</th>
                        <th>Valor Máximo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($partners as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td>{{ $item['pagamento_total'] }}</td>
                            <td>{{ $item['media'] }}</td>
                            <td>{{ $item['min_value_autoaprovation'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <!-- Totais -->
            <div class="total">
                <p>Pagamento Total: R$ {{ $pagamento }}</p>
            </div>
        </div>
    </body>
    </html>
