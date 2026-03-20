<?php
/**
 * Chatbot Knowledge Base — rule-based responses trained on QOR/Core Chain content
 * Each entry: keywords => response
 */

function getChatbotKnowledge(): array {
    return [
        [
            'keywords' => ['biometric', 'face', 'fingerprint', 'face id', 'touch id', 'scan'],
            'response' => "Core Chain uses your device's biometric authentication (Face ID / Touch ID) as your wallet key. Your biometric data is processed entirely inside your phone's Secure Enclave chip — it never leaves your device, never touches the blockchain, and is never stored by Core Chain. One scan = full access to your sovereign wallet."
        ],
        [
            'keywords' => ['seed phrase', 'seed', '12 words', 'recovery phrase', 'mnemonic'],
            'response' => "Core Chain eliminates seed phrases entirely. Over \$200B in crypto has been permanently lost to forgotten seed phrases. With Core Chain, your face is your key — no 12 words to memorize, no paper backups to lose. Your private key lives in your device's Secure Enclave hardware chip."
        ],
        [
            'keywords' => ['token', '$qor', 'qor token', 'tokenomics', 'supply'],
            'response' => "\$QOR has a fixed supply of 250 million tokens — no more will ever be created. The token powers the ecosystem through:\n\n• **Staking** — Earn real yield from merchant processing fees\n• **Node Bonding** — Relayers lock 100K QOR per node\n• **Buyback & Burn** — 1% merchant fee drives automatic deflation\n• **Governance** — Token holders vote on protocol upgrades\n\nValue comes from GDP, not inflation."
        ],
        [
            'keywords' => ['security', 'secure', 'safe', 'vault', 'duress', 'hack'],
            'response' => "Core Chain has 4 layers of security:\n\n1. **Secure Enclave** — Private keys in hardware, never extractable\n2. **Biometric Auth** — Your face/fingerprint is the only key\n3. **On-Chain Verification** — Solana SIMD-0075 native biometric verification\n4. **ZK Proofs** — Identity verification without exposing data\n\nPlus physical defense: Vault Mode (48hr timelock), Duress PIN (decoy wallet), and Dead Man's Switch for estate planning."
        ],
        [
            'keywords' => ['zk', 'zero knowledge', 'privacy', 'compliance', 'kyc', 'aml'],
            'response' => "Core Chain uses Zero-Knowledge Compression for privacy-preserving compliance. Rich personal data enters the ZK Prover — only a 128-byte Merkle Root comes out on-chain. Banks can verify your identity, but the world sees only math. We verify identity for the bank, but publish only math to the world."
        ],
        [
            'keywords' => ['staking', 'yield', 'earn', 'rewards', 'apy'],
            'response' => "Core Chain staking is powered by real merchant revenue, not token inflation. When merchants process payments, 0.1% goes to stakers as yield. This means:\n\n• **Non-dilutive** — No new tokens minted for rewards\n• **Revenue-backed** — More volume = more yield\n• **Compounding** — As burns reduce supply, your share grows\n\nStake via the veQOR contract for up to 300% yield multiplier."
        ],
        [
            'keywords' => ['solana', 'simd', 'blockchain', 'chain', 'network'],
            'response' => "Core Chain is built natively on Solana, leveraging SIMD-0075 for native biometric signature verification. Why Solana?\n\n• **400ms finality** (vs 12 min on Ethereum)\n• **Negligible gas fees**\n• **Native account abstraction** (no alt-mempools)\n• **Unified state** (no bundlers needed)\n\nPlus the EVM Escape Hatch gives cross-chain access to Ethereum, Base, BSC, and more."
        ],
        [
            'keywords' => ['cross chain', 'evm', 'ethereum', 'bridge', 'multi chain'],
            'response' => "Core Chain's EVM Controller Model lets you use one biometric identity across every blockchain. Solana is the Master Controller, with satellite wallets on Ethereum, Base, BSC, and Avalanche. Trustless light client state proofs (PLONK ZK-SNARKs) replace vulnerable multi-sig bridges. One identity, every chain, no bridge risk."
        ],
        [
            'keywords' => ['iso', '20022', 'bank', 'institution', 'regulatory'],
            'response' => "Core Chain natively maps Solana transactions to ISO 20022 (pacs.008) format — the global banking standard replacing SWIFT. Transfer Hooks enforce real-time AML screening via Regulatory Oracles. If a wallet is flagged, the transaction atomically reverts. Banks get compliance certainty, users keep privacy."
        ],
        [
            'keywords' => ['whitepaper', 'paper', 'documentation', 'docs'],
            'response' => "You can read the full Core Chain whitepaper here: [Whitepaper QOR.pdf](Whitepaper%20QOR.pdf)\n\nIt covers the complete technical architecture — biometric cryptography, account abstraction, fee mechanics, ZK compliance, cross-chain interoperability, defensive security, and tokenomics."
        ],
        [
            'keywords' => ['launch', 'when', 'date', 'timeline', 'roadmap'],
            'response' => "Core Chain is targeting a 2026 launch. Join the waitlist on our homepage to get:\n\n• Early access to the biometric wallet\n• Development milestone updates\n• Priority access to the token launch\n\nWe'll keep you updated every step of the way."
        ],
        [
            'keywords' => ['bonding', 'node', 'relayer', 'operator', 'infrastructure'],
            'response' => "To operate a Core Chain relayer node, operators must bond 100,000 QOR tokens. With a target of 500 enterprise nodes, that's 20% of total supply permanently locked. Nodes earn junction fees (0.6% of transactions in USDC). More volume = more demand for nodes = more buy pressure on QOR."
        ],
        [
            'keywords' => ['lost device', 'phone lost', 'recovery', 'dead man'],
            'response' => "If your device is lost:\n\n1. **Multi-device sync** — Register biometrics on a backup device beforehand\n2. **Social recovery** — Trusted contacts initiate time-delayed recovery\n3. **Dead Man's Switch** — If no biometric check-in for X days, funds auto-transfer to your designated beneficiaries\n\nNo seed phrase needed for any recovery path."
        ],
        [
            'keywords' => ['contact', 'support', 'help', 'email', 'reach'],
            'response' => "You can reach the Core Chain team at:\n\n• **Email:** hello@corechain.io\n• **Twitter/X:** [@QOR_network](https://x.com/QOR_network)\n• **Telegram:** [t.me/QOR_Networks](https://t.me/QOR_Networks)\n\nOr use the contact form on our website — we respond within 48 hours."
        ],
        [
            'keywords' => ['price', 'buy', 'invest', 'exchange', 'listing'],
            'response' => "The QOR token hasn't launched yet — we're targeting 2026. Join the waitlist to be first in line. We don't provide price predictions or investment advice. The token's value is designed to be driven by real merchant transaction volume through the buyback & burn mechanism."
        ],
    ];
}

function findBotResponse(string $userMessage): ?string {
    $message = strtolower(trim($userMessage));
    $knowledge = getChatbotKnowledge();

    $bestMatch = null;
    $bestScore = 0;

    foreach ($knowledge as $entry) {
        $score = 0;
        foreach ($entry['keywords'] as $keyword) {
            if (str_contains($message, strtolower($keyword))) {
                $score += strlen($keyword); // Longer keyword matches = higher score
            }
        }
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $entry['response'];
        }
    }

    return $bestScore >= 3 ? $bestMatch : null;
}
