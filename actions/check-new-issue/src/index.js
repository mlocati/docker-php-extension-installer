const core = require('@actions/core');
const github = require('@actions/github');

const VERSION_PREVLINE = '### Version of install-php-extensions';

/**
 * @param {string} body
 *
 * @returns {string}
 */
function extractVersionLine(body)
{
  const lines = body.split(/[\r\n]+/).map((line) => line.trim()).filter((line) => line !== '');
  for (let i = 0; i < lines.length - 1; i++) {
    if (lines[i] === VERSION_PREVLINE) {
      return lines[i + 1];
    }
  }
  throw new Error(`Failed to find the line\n${VERSION_PREVLINE}\nin\n${body}`);
}

/**
 * @param {string} versionLine
 *
 * @returns {string}
 */
function extractVersion(versionLine)
{
  return versionLine
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/^install-php-extensions\s*\b/, '')
    .replace(/^v(e(r(s(i(o(n)?)?)?)?)?)?(\s*\.)?\s*/i, '')
  ;
}

/**
 * @param {string} version
 * @param {string} versionLine
 *
 * @returns {string}
 */
function getCloseReason(version, versionLine)
{
  let prefix = '';
  if (versionLine !== '') {
    prefix = '> ' + [
      VERSION_PREVLINE,
      '',
      versionLine,
    ].join('\n> ') + '\n\n';
  }
  if (version === '') {
    return prefix + `@${github.context.actor} please specify the \`install-php-extensions\` version you are using: it's the first line printed by the script.\n\nFor example:\n\`\`\`\ninstall-php-extensions v.X.Y.Z\n\`\`\``;
  }
  if (!/^\d+\.\d+\.\d+$/.test(version)) {
    return prefix + `@${github.context.actor} the \`install-php-extensions\` version that you specified (\`${version}\`) is not valid.\n\nPlease specify the \`install-php-extensions\` version you are using: it's the first line printed by the script.\nFor example:\n\n\`\`\`\ninstall-php-extensions v.X.Y.Z\n\`\`\``;
  }
  return '';
}

/**
 * @param {string} version
 * @param {string} versionLine
 * @param {string} token
 *
 * @returns {string}
 */
async function getComment(version, versionLine, token)
{
  let prefix = '';
  if (versionLine !== '') {
    prefix = '> ' + [
      VERSION_PREVLINE,
      '',
      versionLine,
    ].join('\n> ') + '\n\n'
  }
  const octokit = github.getOctokit(token);
  const { owner, repo } = github.context.repo;
  const { data: latestRelease } = await octokit.rest.repos.getLatestRelease({owner, repo});
  const latestVersion = latestRelease.tag_name.replace(/^(v\.?)?/, '');
  console.log(`Latest version: ${latestVersion}`);
  if (version !== latestVersion) {
    return prefix + `@${github.context.actor} the \`install-php-extensions\` version that you specified (\`${version}\`) is not the most recent one (\`${latestVersion}\`).\n\nHave you checked whether your issue still persists in the very latest version of \`install-php-extensions\`?`;
  }

  return '';
}

/**
 * @returns {Promise|undefined}
 */
async function main()
{
  try {
    const token = core.getInput('token', {required: true, trimWhitespace: true});
    const body = github.context.payload.issue.body;
    const versionLine = extractVersionLine(body);
    console.log(`Version line: ${versionLine}`);
    const version = extractVersion(versionLine);
    console.log(`version: ${version}`);
    const closeReason = getCloseReason(version, versionLine);
    const octokit = github.getOctokit(token);
    if (closeReason !== '') {
      const { owner, repo } = github.context.repo;
      await octokit.rest.issues.createComment({
        owner,
        repo,
        issue_number: github.context.payload.issue.number,
        body: closeReason,
      })
      return await octokit.rest.issues.update({
        owner,
        repo,
        state: 'closed',
        state_reason: 'not_planned',
        issue_number: github.context.payload.issue.number,
      });
    }
    const comment = await getComment(version, versionLine, token);
    if (comment !== '') {
      const { owner, repo } = github.context.repo;
      await octokit.rest.issues.createComment({
        owner,
        repo,
        issue_number: github.context.payload.issue.number,
        body: comment,
      })
    }    
  } catch (e) {
    core.setFailed(e?.message || e?.toString() || 'Unknown error');
  }
}

return main();
