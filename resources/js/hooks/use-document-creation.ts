import { useState } from 'react';
import { documentOperation } from '@/lib/document-operation';

export function useDocumentCreation() {
    const [operation] = useState(() => documentOperation());

    return operation;
}
