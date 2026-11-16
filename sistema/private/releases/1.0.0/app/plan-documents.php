<?php
declare(strict_types=1);

/** Returns only a plan visible within the authenticated municipal scope. */
function importedPlanForUser(array $user, string $id): array
{
    if ($user['role'] === 'platform' || empty($user['tenant'])) {
        throw new Fault('Documento não encontrado neste acesso.', 404);
    }
    $plan = record($user['tenant'], 'plans', $id);
    if (!$plan || (!empty($user['power']) && $user['power'] !== $plan['power'])) {
        throw new Fault('Documento não encontrado neste acesso.', 404);
    }
    if (in_array($user['role'], ['employee', 'commission'], true) || !empty($user['unit'])) {
        $allowed = false;
        foreach (records($user['tenant'], 'links') as $link) {
            if ($link['planId'] === $id && visibleLink($user, $link)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new Fault('Documento não encontrado neste acesso.', 404);
        }
    }
    return $plan;
}

function serveImportedPlan(array $user, string $id): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
        throw new Fault('Método não permitido.', 405);
    }
    $plan = importedPlanForUser($user, $id);
    $document = $plan['sourceDocument'] ?? [];
    $fileId = $document['id'] ?? '';
    if (!preg_match('/^[a-f0-9]{32}$/D', $fileId)) {
        throw new Fault('Documento não encontrado.', 404);
    }
    $file = TR_PRIVATE.'/data/'.$fileId.'.enc';
    if (!is_file($file)) {
        throw new Fault('Documento indisponível.', 404);
    }
    $bytes = unseal((string) file_get_contents($file));
    if (!hash_equals($document['sha256'] ?? '', hash('sha256', $bytes))) {
        throw new Fault('Integridade do documento não confirmada.', 500);
    }
    audit($user['tenant'], 'plan.document.view', $id);
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="plano-de-carreira.pdf"');
    header('Cache-Control: no-store, private');
    echo $bytes;
    exit;
}
