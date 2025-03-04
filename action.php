<?php
if (!defined('DOKU_INC')) die();

class action_plugin_llm extends DokuWiki_Action_Plugin {

    public function register(Doku_Event_Handler $controller) {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'handle_edit_page');
    }

    public function handle_edit_page(Doku_Event &$event, $param) {
        if ($event->data !== 'edit') return;

        global $ID;
        $textarea_id = 'wiki__text';

        // Inject HTML and JavaScript
        echo '<div id="llm-plugin-container" style="margin-bottom: 10px;">';
        echo '<div id="llm-wizard-output" style="display: none; margin-top: 10px; max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 5px; resize: vertical; min-height: 100px;">';
        echo '<div id="llm-output-container"></div>';
        echo '<div style="margin-top: 5px;">';
        echo '<button id="llm-clear-output" style="margin: 5px;" title="Clear all results">🗑️</button>';
        echo '<button id="llm-copy-all" style="margin: 5px;" title="Copy all visible text to clipboard">📋</button>';
        echo '<button id="llm-toggle-buttons" style="margin: 5px;" title="Toggle visibility of line buttons">👁️</button>';
        echo '<button id="llm-paste-all" style="margin: 5px;" title="Paste all visible lines to main textarea">📥</button>';
        echo '</div>';
        echo '</div>';
        echo '<div id="llm-status" style="color: #555;"></div>';
        echo '<select id="llm-model-select" style="margin-right: 10px;">';
        echo '<option value="">Select a model</option>';
        echo '<option value="https://huggingface.co/bartowski/Reasoning-0.5b-GGUF/resolve/main/Reasoning-0.5b-Q6_K.gguf|GGUF_CPU">Reasoning-0.5B-Q6_K|GGUF_CPU</option>';
        echo '<option value="https://huggingface.co/Qwen/Qwen2-0.5B-Instruct-GGUF/resolve/main/qwen2-0_5b-instruct-q4_0.gguf|GGUF_CPU">Qwen2-0.5B-Instruct (353 MB)</option>';
        echo '<option value="https://huggingface.co/rahuldshetty/llm.js/resolve/main/TinyMistral-248M-SFT-v4.Q8_0.gguf|GGUF_CPU">TinyMistral-248M-SFT-v4 (264 MB)</option>';
        echo '<option value="https://huggingface.co/rahuldshetty/llm.js/resolve/main/llama2_xs_460m_experimental_evol_instruct.q4_k_m.gguf|GGUF_CPU">LLaMa Lite (289 MB)</option>';
        echo '<option value="https://huggingface.co/rahuldshetty/llm.js/resolve/main/tiny-llama-miniguanaco-1.5t.q2_k.gguf|GGUF_CPU">TinyLLama 1.5T (482 MB)</option>';
        echo '<option value="https://huggingface.co/afrideva/TinyMistral-248M-Alpaca-GGUF/resolve/main/tinymistral-248m-alpaca.q4_k_m.gguf|GGUF_CPU">TinyMistral-248M-Alpaca (156 MB)</option>';
        echo '<option value="https://huggingface.co/unsloth/DeepSeek-R1-Distill-Qwen-1.5B-GGUF/resolve/main/DeepSeek-R1-Distill-Qwen-1.5B-Q6_K.gguf|GGUF_CPU">DeepSeek-R1-Distill-Qwen-1.5B|GGUF_CPU</option>';
        echo '<option value="https://huggingface.co/bartowski/Reasoning-0.5b-GGUF/resolve/main/Reasoning-0.5b-Q4_K_M.gguf|GGUF_CPU">Reasoning-0.5B-Q4_K_M</option>';
        // Additional models compatible with llm.js
        echo '<option value="https://huggingface.co/QuantFactory/Phi-3-mini-4k-instruct-GGUF/resolve/main/Phi-3-mini-4k-instruct.Q4_K_M.gguf|GGUF_CPU">Phi-3-mini-4k-instruct (2.2 GB)</option>';
        echo '<option value="https://huggingface.co/bartowski/Meta-Llama-3.1-8B-Instruct-GGUF/resolve/main/Meta-Llama-3.1-8B-Instruct-Q4_K_M.gguf|GGUF_CPU">Llama-3.1-8B-Instruct-Q4_K_M (4.7 GB)</option>';
        echo '<option value="https://huggingface.co/bartowski/gemma-2-2b-it-GGUF/resolve/main/gemma-2-2b-it-Q4_K_M.gguf|GGUF_CPU">Gemma-2-2B-IT-Q4_K_M (1.3 GB)</option>';
        echo '<option value="https://huggingface.co/bartowski/Mixtral-8x7B-Instruct-v0.1-GGUF/resolve/main/Mixtral-8x7B-Instruct-v0.1-Q2_K.gguf|GGUF_CPU">Mixtral-8x7B-Instruct-Q2_K (12 GB)</option>';
        echo '<option value="custom">Custom Model (Hugging Face URL)</option>';
        echo '</select>';
        echo '<input type="text" id="llm-custom-url" placeholder="Enter Hugging Face GGUF URL" style="display: none; width: 300px; margin-left: 10px;">';
        echo '<progress id="llm-progress" hidden style="width: 100%;"></progress>';
        echo '<div id="llm-options" style="display: none; margin: 5px 0;">';
        echo '<div style="display: flex; align-items: center; margin-bottom: 5px;">';
        echo '<textarea id="llm-prompt" placeholder="Enter your prompt here..." style="width: 100%; height: 50px; margin-right: 5px;"></textarea>';
        echo '<button id="llm-send-prompt" style="height: 50px;" title="Send prompt to AI">✈️</button>';
        echo '</div>';
        echo '<button id="llm-toggle-advanced" style="margin-bottom: 5px;">Show Advanced Options</button>';
        echo '<div id="llm-advanced-options" style="display: none;">';
        echo '<div style="display: flex; flex-wrap: wrap; gap: 10px;">';
        echo '<label>Top-K (1-100): <span id="topk-value">1</span> <input type="range" id="llm-topk" min="1" max="100" step="1" value="1"></label>';
        echo '<label>Temperature (0-2): <span id="temp-value">1</span> <input type="range" id="llm-temperature" min="0" max="2" step="0.1" value="1"></label>';
        echo '<label>Max Tokens (1-500): <span id="maxtoken-value">50</span> <input type="range" id="llm-maxtoken" min="1" max="500" step="1" value="50"></label>';
        echo '<label>Top-P (0-1): <span id="topp-value">0.9</span> <input type="range" id="llm-topp" min="0" max="1" step="0.05" value="0.9"></label>';
        echo '<label>Context Size (1-2048): <span id="context-value">512</span> <input type="range" id="llm-context" min="1" max="2048" step="1" value="512"></label>';
        echo '</div>';
        echo '<textarea id="llm-grammar" placeholder="Optional GBNF grammar (e.g., root ::= [a-z]+)" style="width: 100%; height: 50px; margin-top: 5px;"></textarea>';
        echo '<textarea id="llm-prompt-template" placeholder="Prompt template (use ${prompt} for input)" style="width: 100%; height: 50px; margin-top: 5px;"><|im_start|>user\n${prompt}<|im_end|>\n<|im_start|>assistant\n</textarea>';
        echo '</div>';
        echo '<button id="llm-summarizer" style="margin: 5px;" title="Summarize selected or all text">📝</button>';
        echo '<button id="llm-proofreader" style="margin: 5px;" title="Proofread selected or all text">✏️</button>';
        echo '<button id="llm-translator" style="margin: 5px;" title="Translate selected or all text">🌐</button>';
        echo '<select id="llm-language" style="display: none; margin: 5px;">';
        echo '<option value="">Select Language</option>';
        echo '<option value="en">English</option>';
        echo '<option value="zh-TW">Traditional Chinese</option>';
        echo '<option value="zh-CN">Simplified Chinese</option>';
        echo '<option value="ja">Japanese</option>';
        echo '<option value="fr">French</option>';
        echo '<option value="es">Spanish</option>';
        echo '<option value="de">German</option>';
        echo '</select>';
        echo '<select id="llm-output-format" style="margin: 5px;">';
        echo '<option value="raw">Raw Text</option>';
        echo '<option value="prefix">[Assistant] </option>';
        echo '<option value="timestamp">[Time] </option>';
        echo '</select>';
        echo '</div>';
        echo '</div>';

        // Inject llm.js and custom script
        ?>
        <script type="module">
            import { LLM } from '<?php echo DOKU_BASE; ?>lib/plugins/llm/llm.js/llm.js';

            let LLMEngine;
            const modelSelect = document.getElementById('llm-model-select');
            const customUrl = document.getElementById('llm-custom-url');
            const promptTextarea = document.getElementById('llm-prompt');
            const sendPromptButton = document.getElementById('llm-send-prompt');
            const progress = document.getElementById('llm-progress');
            const optionsDiv = document.getElementById('llm-options');
            const advancedOptionsDiv = document.getElementById('llm-advanced-options');
            const toggleAdvancedButton = document.getElementById('llm-toggle-advanced');
            const status = document.getElementById('llm-status');
            const mainTextarea = document.getElementById('<?php echo $textarea_id; ?>');
            const topkSlider = document.getElementById('llm-topk');
            const tempSlider = document.getElementById('llm-temperature');
            const maxTokenSlider = document.getElementById('llm-maxtoken');
            const topPSlider = document.getElementById('llm-topp');
            const contextSlider = document.getElementById('llm-context');
            const grammarTextarea = document.getElementById('llm-grammar');
            const topkValue = document.getElementById('topk-value');
            const tempValue = document.getElementById('temp-value');
            const maxTokenValue = document.getElementById('maxtoken-value');
            const topPValue = document.getElementById('topp-value');
            const contextValue = document.getElementById('context-value');
            const wizardOutputDiv = document.getElementById('llm-wizard-output');
            const outputContainer = document.getElementById('llm-output-container');
            const clearOutputButton = document.getElementById('llm-clear-output');
            const copyAllButton = document.getElementById('llm-copy-all');
            const toggleButtons = document.getElementById('llm-toggle-buttons');
            const pasteAllButton = document.getElementById('llm-paste-all');
            const languageSelect = document.getElementById('llm-language');
            const outputFormatSelect = document.getElementById('llm-output-format');
            const promptTemplateTextarea = document.getElementById('llm-prompt-template');

            let buttonsHidden = false;

            // Update slider values
            [topkSlider, tempSlider, maxTokenSlider, topPSlider, contextSlider].forEach(slider => {
                slider.addEventListener('input', () => {
                    document.getElementById(slider.id.replace('llm-', '') + '-value').textContent = slider.value;
                });
            });

            // Model selection and custom URL
            modelSelect.addEventListener('change', () => {
                const value = modelSelect.value;
                customUrl.style.display = value === 'custom' ? 'inline' : 'none';
                optionsDiv.style.display = 'none';
                wizardOutputDiv.style.display = 'none';
                if (!value) return;

                let url = value === 'custom' ? customUrl.value : value.split('|')[0];
                const type = value === 'custom' ? 'GGUF_CPU' : value.split('|')[1];
                if (!url) return;

                status.textContent = 'Loading model...';
                progress.hidden = false;

                LLMEngine = new LLM(
                    type,
                    url,
                    () => {
                        status.textContent = 'Model loaded successfully!';
                        progress.hidden = true;
                        optionsDiv.style.display = 'block';
                        wizardOutputDiv.style.display = 'block';
                    },
                    (line) => {
                        console.log('Progress:', line);
                        const cleanLine = line.replace(/<\|im_(start|end)\|>/g, '').trim();
                        if (cleanLine) {
                            const formattedText = formatOutput(cleanLine);
                            if (formattedText) addOutput(formattedText, false);
                        }
                    },
                    () => {
                        status.textContent = 'Generation complete.';
                    },
                    {
                        wasmUrl: '<?php echo DOKU_BASE; ?>lib/plugins/llm/llm.js/llamacpp-cpu.js',
                        workerUrl: '<?php echo DOKU_BASE; ?>lib/plugins/llm/llm.js/llm.worker.js'
                    }
                );

                try {
                    LLMEngine.load_worker();
                } catch (error) {
                    status.textContent = `Error initializing worker: ${error.message}`;
                    console.error('Worker initialization error:', error);
                    progress.hidden = true;
                }
            });

            // Send prompt function
            function sendPrompt() {
                const prompt = promptTextarea.value.trim();
                if (prompt && LLMEngine) {
                    status.textContent = 'Generating...';
                    promptTextarea.value = '';
                    const template = promptTemplateTextarea.value.trim();
                    const formattedPrompt = template.replace('${prompt}', prompt);
                    runLLM(formattedPrompt);
                } else if (!LLMEngine) {
                    status.textContent = 'Please select a model first.';
                }
            }

            // Prompt generation on Enter
            promptTextarea.addEventListener('keypress', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendPrompt();
                }
            });

            // Send prompt button
            sendPromptButton.addEventListener('click', sendPrompt);

            // Toggle advanced options
            toggleAdvancedButton.addEventListener('click', () => {
                const isHidden = advancedOptionsDiv.style.display === 'none';
                advancedOptionsDiv.style.display = isHidden ? 'block' : 'none';
                toggleAdvancedButton.textContent = isHidden ? 'Hide Advanced Options' : 'Show Advanced Options';
            });

            // AI Wizard buttons
            document.getElementById('llm-summarizer').addEventListener('click', () => runWizard('summarize'));
            document.getElementById('llm-proofreader').addEventListener('click', () => runWizard('proofread'));
            document.getElementById('llm-translator').addEventListener('click', () => {
                languageSelect.style.display = 'inline';
                if (!languageSelect.value) {
                    status.textContent = 'Please select a language for translation.';
                    return;
                }
                runWizard('translate');
            });

            // Clear all results
            clearOutputButton.addEventListener('click', () => {
                outputContainer.innerHTML = '';
                status.textContent = 'Results cleared.';
            });

            // Copy all visible text
            copyAllButton.addEventListener('click', () => {
                const visibleText = Array.from(outputContainer.querySelectorAll('.output-div'))
                    .filter(div => div.style.display !== 'none')
                    .map(div => div.querySelector('span').textContent)
                    .join('');
                navigator.clipboard.writeText(visibleText).then(() => {
                    status.textContent = 'Copied all visible text to clipboard!';
                    setTimeout(() => status.textContent = '', 2000);
                });
            });

            // Paste all visible text to main textarea
            pasteAllButton.addEventListener('click', () => {
                const visibleText = Array.from(outputContainer.querySelectorAll('.output-div'))
                    .filter(div => div.style.display !== 'none')
                    .map(div => div.querySelector('span').textContent)
                    .join('');
                const startPos = mainTextarea.selectionStart;
                const endPos = mainTextarea.selectionEnd;
                mainTextarea.value = mainTextarea.value.substring(0, startPos) + visibleText + mainTextarea.value.substring(endPos);
                status.textContent = 'Pasted all visible text to textarea!';
                setTimeout(() => status.textContent = '', 2000);
            });

            // Toggle copy/remove/paste buttons
            toggleButtons.addEventListener('click', () => {
                buttonsHidden = !buttonsHidden;
                const buttons = outputContainer.querySelectorAll('.output-div button');
                buttons.forEach(button => {
                    button.style.display = buttonsHidden ? 'none' : 'inline';
                });
                toggleButtons.textContent = buttonsHidden ? '👁️' : '👁️‍🗨️';
            });

            // Format output based on user selection
            function formatOutput(text) {
                const format = outputFormatSelect.value;
                switch (format) {
                    case 'raw': return text.trim() + '\n';
                    case 'prefix': return '[Assistant] ' + text.trim() + '\n';
                    case 'timestamp': return `[${new Date().toLocaleTimeString()}] ` + text.trim() + '\n';
                    default: return text.trim() + '\n';
                }
            }

            // LLM run function
            function runLLM(prompt, callback = addOutput, onComplete = () => status.textContent = 'Generation complete.', isUserPrompt = false) {
                LLMEngine.run({
                    prompt: prompt,
                    max_token_len: parseInt(maxTokenSlider.value),
                    top_k: parseInt(topkSlider.value),
                    top_p: parseFloat(topPSlider.value),
                    temp: parseFloat(tempSlider.value),
                    context_size: parseInt(contextSlider.value),
                    grammar: grammarTextarea.value.trim() || '',
                    write_result_callback: (line) => {
                        const cleanLine = line.replace(/<\|im_(start|end)\|>/g, '').trim();
                        if (cleanLine) {
                            const formattedText = formatOutput(cleanLine);
                            if (formattedText) callback(formattedText, isUserPrompt);
                        }
                    },
                    on_complete_callback: onComplete
                });
            }

            // Add output to wizard div
            function addOutput(text, isUserPrompt = false) {
                const outputDiv = document.createElement('div');
                outputDiv.className = 'output-div';
                outputDiv.style.border = '1px solid #ddd';
                outputDiv.style.padding = '5px';
                outputDiv.style.margin = '5px 0';
                outputDiv.style.display = 'flex';
                outputDiv.style.alignItems = 'center';
                if (isUserPrompt) outputDiv.setAttribute('data-user-input', 'true');

                const textSpan = document.createElement('span');
                textSpan.textContent = text;
                textSpan.style.flexGrow = '1';

                const copyButton = document.createElement('button');
                copyButton.textContent = '📋';
                copyButton.title = 'Copy this line to clipboard';
                copyButton.style.marginLeft = '10px';
                copyButton.addEventListener('click', () => {
                    navigator.clipboard.writeText(text.trim()).then(() => {
                        status.textContent = 'Copied to clipboard!';
                        setTimeout(() => status.textContent = '', 2000);
                    });
                });

                const removeButton = document.createElement('button');
                removeButton.textContent = '🗑️';
                removeButton.title = 'Remove this line';
                removeButton.style.marginLeft = '10px';
                removeButton.addEventListener('click', () => {
                    outputDiv.remove();
                    status.textContent = 'Line removed.';
                });

                const pasteButton = document.createElement('button');
                pasteButton.textContent = '📥';
                pasteButton.title = 'Paste this line to main textarea';
                pasteButton.style.marginLeft = '10px';
                pasteButton.addEventListener('click', () => {
                    const startPos = mainTextarea.selectionStart;
                    const endPos = mainTextarea.selectionEnd;
                    // Append newline after the pasted text
                    mainTextarea.value = mainTextarea.value.substring(0, startPos) + text.trim() + '\n' + mainTextarea.value.substring(endPos);
                    // Move cursor after the pasted text and newline
                    mainTextarea.selectionStart = mainTextarea.selectionEnd = startPos + text.trim().length + 1;
                    status.textContent = 'Pasted to textarea!';
                    setTimeout(() => status.textContent = '', 2000);
                });

                outputDiv.appendChild(textSpan);
                outputDiv.appendChild(copyButton);
                outputDiv.appendChild(pasteButton);
                outputDiv.appendChild(removeButton);
                outputContainer.appendChild(outputDiv);
                wizardOutputDiv.scrollTop = wizardOutputDiv.scrollHeight;
            }

            // AI Wizard logic
            async function runWizard(mode) {
                if (!LLMEngine) {
                    status.textContent = 'Please load a model first.';
                    return;
                }
                const text = mainTextarea.value;
                const selectedText = text.substring(mainTextarea.selectionStart, mainTextarea.selectionEnd);

                if (selectedText) {
                    const prompt = getPrompt(mode, selectedText);
                    status.textContent = `Running ${mode}...`;
                    runLLM(prompt, addOutput, () => status.textContent = `${mode} complete.`);
                } else {
                    const lines = text.split('\n');
                    const chunkSize = Math.ceil(parseInt(maxTokenSlider.value) / 2);
                    const chunks = [];
                    for (let i = 0; i < lines.length; i += chunkSize) {
                        chunks.push(lines.slice(i, i + chunkSize).join('\n'));
                    }
                    for (let i = 0; i < chunks.length; i++) {
                        const prompt = getPrompt(mode, chunks[i]);
                        status.textContent = `Running ${mode} (${i + 1}/${chunks.length})...`;
                        await new Promise(resolve => {
                            runLLM(prompt, addOutput, () => resolve());
                        });
                    }
                    status.textContent = `${mode} complete.`;
                }
            }

            // Generate prompt based on mode
            function getPrompt(mode, text) {
                const template = promptTemplateTextarea.value.trim();
                switch (mode) {
                    case 'summarize': return template.replace('${prompt}', `Summarize the paragraphs:\n${text}`);
                    case 'proofread': return template.replace('${prompt}', `Proofread this text and suggest corrections:\n${text}`);
                    case 'translate': return template.replace('${prompt}', `Translate this text to ${languageSelect.value}:\n${text}`);
                }
            }
        </script>
        <?php
    }
}