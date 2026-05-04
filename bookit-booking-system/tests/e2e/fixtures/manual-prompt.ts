import * as readline from 'readline';

// Used in smoke tests for steps requiring human action on the live site.
export async function manualConfirm(message: string): Promise<boolean> {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  return new Promise((resolve) => {
    rl.question(`\n⏸  [MANUAL] ${message}\n   Press Y to confirm, N to fail: `, (answer) => {
      rl.close();
      resolve(answer.trim().toUpperCase() === 'Y');
    });
  });
}
