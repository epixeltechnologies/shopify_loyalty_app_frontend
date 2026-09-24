import { Modal, Text } from '@shopify/polaris';

interface Props {
  open: boolean;
  title: string;
  message: string;
  confirmLabel?: string;
  destructive?: boolean;
  loading?: boolean;
  onConfirm: () => void;
  onClose: () => void;
}

/**
 * Generic confirmation dialog — reused wherever a future feature needs
 * "are you sure?" before a destructive or hard-to-undo action (e.g.
 * archiving a reward, cancelling a subscription). Deliberately generic
 * (title/message/confirmLabel as props) rather than one-off Modal JSX
 * duplicated per page.
 */
export function ConfirmModal({
  open,
  title,
  message,
  confirmLabel = 'Confirm',
  destructive = false,
  loading = false,
  onConfirm,
  onClose,
}: Props) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title={title}
      primaryAction={{
        content: confirmLabel,
        destructive,
        loading,
        onAction: onConfirm,
      }}
      secondaryActions={[{ content: 'Cancel', onAction: onClose }]}
    >
      <Modal.Section>
        <Text as="p">{message}</Text>
      </Modal.Section>
    </Modal>
  );
}
