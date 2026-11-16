<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Documento;
use App\Models\Obrigacao;
use App\Services\Upload;
use Throwable;

/**
 * Documentos ligados à obrigação: o cliente anexa o comprovante na guia
 * certa e o contador baixa de lá. Ambos os perfis, sempre conferindo a empresa.
 */
final class DocumentoController extends Controller
{
    public function enviar(): void
    {
        $obrigacaoId = $this->idOu404($_POST, 'obrigacao_id');
        $obrigacao = (new Obrigacao())->buscar($obrigacaoId) ?? $this->naoEncontrado();
        $this->autorizarEmpresa((int) $obrigacao['empresa_id']);

        $arquivo = $_FILES['arquivo'] ?? null;
        $erro = Upload::validar($arquivo);

        if ($erro !== null) {
            $this->flash('danger', $erro);
            $this->voltar($obrigacaoId);
        }

        $salvo = Upload::salvar($arquivo);
        try {
            (new Documento())->criar($obrigacaoId, Auth::usuario()['id'], $salvo);
        } catch (Throwable $falha) {
            Upload::apagar($salvo['arquivo']); // não deixa arquivo órfão no disco
            throw $falha;
        }

        $this->flash('success', 'Documento "' . $salvo['nome_original'] . '" enviado.');
        $this->voltar($obrigacaoId);
    }

    /**
     * Entrega o arquivo pelo PHP (e não por link direto): assim dá para
     * conferir login e empresa a cada download.
     */
    public function baixar(): void
    {
        $documento = (new Documento())->buscar($this->idOu404($_GET)) ?? $this->naoEncontrado();
        $this->autorizarEmpresa((int) $documento['empresa_id']);

        $caminho = Upload::caminho($documento['arquivo']) ?? $this->naoEncontrado();
        $nome = $documento['nome_original'];
        // Versão só com ASCII para navegadores antigos + versão UTF-8 (RFC 6266).
        $nomeAscii = (string) preg_replace('/[^A-Za-z0-9._-]/u', '_', $nome);

        header('Content-Type: ' . $documento['mime']);
        header('Content-Length: ' . filesize($caminho));
        header('Content-Disposition: attachment; filename="' . $nomeAscii . '"; filename*=UTF-8\'\'' . rawurlencode($nome));
        header('Cache-Control: private, no-store');
        readfile($caminho);
        exit;
    }

    private function voltar(int $obrigacaoId): never
    {
        header('Location: ' . url('obrigacoes/ver', ['id' => $obrigacaoId]) . '#documentos');
        exit;
    }
}
