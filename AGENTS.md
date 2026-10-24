# Developer identity and Git commits

- Developer: Imran Ahmed <me@imrandev.bd>.
- Official developer website: https://imrandev.bd.
- Use Imran Ahmed and me@imrandev.bd for both author and committer on all new commits. Never substitute an AI agent identity.
- Before each commit, run `git config user.name`, `git config user.email`, `git var GIT_AUTHOR_IDENT`, and `git var GIT_COMMITTER_IDENT`. Both effective identities must match the developer identity above. Correct configuration or environment overrides before committing if necessary.
- Configure missing Git defaults with `git config --global user.name "Imran Ahmed"` and `git config --global user.email "me@imrandev.bd"`. Check repository overrides as well.
- Preserve existing Git history unless the user explicitly requests rewriting it.
- Use the official website in appropriate first-party metadata, documentation, and author information.
- Preserve existing third-party authorship, copyright notices, and licensing information.
