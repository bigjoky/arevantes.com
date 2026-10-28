import type { ComponentProps } from 'react';
import BanksWorkspace from '../finance/banks';
import LegacyBanking from './legacy-index';
export default function BankingIndex(
    props: ComponentProps<typeof LegacyBanking> &
        ComponentProps<typeof BanksWorkspace>,
) {
    return props.financeAccounts || props.bankTab === 'reconciliation' ? (
        <BanksWorkspace {...props} />
    ) : (
        <LegacyBanking {...props} />
    );
}
BankingIndex.layout = LegacyBanking.layout;
