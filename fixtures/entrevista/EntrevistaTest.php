<?php

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

final class EntrevistaTest extends TestCase
{
    use DatabaseMigrations;

    private function importar(string $archivo)
    {
        // Mantener {} como objeto y el contrato HTTP del importador.
        $dataset = json_decode(file_get_contents(__DIR__.'/'.$archivo), false, 64, JSON_THROW_ON_ERROR);
        self::assertCount($dataset->count, $dataset->quotes);
        return $this->call('POST', '/quotes/batch', [], [], [], ['CONTENT_TYPE' => 'application/json'],
            json_encode(['quotes' => $dataset->quotes], JSON_THROW_ON_ERROR));
    }

    public function test_recorrido_de_entrevista(): void
    {
        $rows = $this->importar('01-recorrido.v2.json')->assertCreated()->assertJsonPath('imported', 6)->json('quotes');
        self::assertEquals([4722388, 96.80, 94.70, 600, 0.05, 1500], array_column($rows, 'total'));
        $amounts = [[-119500,-391500,-436750,-50000,22050,819588],[-30,10,16.80],[-30,14.70,10],[-100,0],[],[]];
        foreach ($rows as $i => $row) {
            $detail = $this->get('/quotes/'.$row['id'])->assertOk()->json();
            self::assertEquals($amounts[$i], array_column($detail['breakdown'], 'amount'));
        }
        self::assertSame(['Pantalla LED 6×3 m con', 'operador técnico'], $detail['items'][0]['description_lines']);
        $this->get('/quotes?client=LABORATORIO&currency=USD')->assertOk()
            ->assertJsonPath('meta.total', 6)->assertJsonPath('data.0.total', 3256.82)->assertJsonPath('data.5.total', null);
        $this->importar('02-rechazo.v2.json')->assertStatus(422)->assertJsonValidationErrors(['quotes.1.rules.0.type']);
        $this->assertDatabaseCount('quotes', 6);
    }
}
