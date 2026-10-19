import { apiPost } from "@/lib/api";

export type MessageInput = {
  name: string;
  contact: string;
  message: string;
};

/**
 * Sends a note from the contact page. The server works out whether `contact`
 * is an email address or a phone number.
 */
export function sendMessage(input: MessageInput): Promise<{ ok: true; message: string }> {
  return apiPost<{ ok: true; message: string }>("/api/v1/messages", {
    ...input,
    website: "",
  });
}
