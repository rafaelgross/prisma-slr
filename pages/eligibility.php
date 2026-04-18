<?php
/**
 * PRISMA-SLR - Tela de Elegibilidade (texto completo)
 * Reutiliza o componente de screening com phase=eligibility
 */
$_GET['phase'] = 'eligibility';
include __DIR__ . '/screening.php';
