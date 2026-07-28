package ke.co.intellicash.android.ui.ledger

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.LedgerEntry
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.ui.components.ErrorScreen
import ke.co.intellicash.android.ui.components.LoadingScreen
import ke.co.intellicash.android.ui.components.formatKes
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class LedgerUiState {
    data object Loading : LedgerUiState()
    data class Loaded(val entries: List<LedgerEntry>) : LedgerUiState()
    data class Error(val message: String) : LedgerUiState()
}

@HiltViewModel
class LedgerViewModel @Inject constructor(
    private val repository: IntelliCashRepository,
    savedStateHandle: SavedStateHandle
) : ViewModel() {
    private val groupId: String = savedStateHandle["groupId"] ?: ""
    private val _state = MutableStateFlow<LedgerUiState>(LedgerUiState.Loading)
    val state = _state.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            _state.value = LedgerUiState.Loading
            when (val result = repository.getGroupLedger(groupId)) {
                is ApiResult.Success -> _state.value = LedgerUiState.Loaded(result.data)
                is ApiResult.Error -> _state.value = LedgerUiState.Error(result.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun LedgerScreen(
    onBack: () -> Unit,
    viewModel: LedgerViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Ledger") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when (val s = state) {
            is LedgerUiState.Loading -> LoadingScreen(modifier = Modifier.padding(padding))
            is LedgerUiState.Error -> ErrorScreen(s.message, viewModel::load, Modifier.padding(padding))
            is LedgerUiState.Loaded -> {
                if (s.entries.isEmpty()) {
                    Box(modifier = Modifier.padding(padding).fillMaxSize().padding(24.dp)) {
                        Text("No ledger entries", style = MaterialTheme.typography.bodyLarge)
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(4.dp)
                    ) {
                        items(s.entries, key = { it.id }) { entry ->
                            LedgerEntryItem(entry)
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun LedgerEntryItem(entry: LedgerEntry) {
    val isCredit = entry.direction == "CREDIT"
    ListItem(
        headlineContent = {
            Text(
                entry.type.replace("_", " "),
                fontWeight = FontWeight.Medium,
                style = MaterialTheme.typography.bodyMedium
            )
        },
        supportingContent = {
            Text(
                entry.description,
                style = MaterialTheme.typography.bodySmall,
                maxLines = 2
            )
        },
        leadingContent = {
            Icon(
                if (isCredit) Icons.Default.ArrowDownward else Icons.Default.ArrowUpward,
                contentDescription = null,
                tint = if (isCredit) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error
            )
        },
        trailingContent = {
            Text(
                text = "${if (isCredit) "+" else "-"}${formatKes(entry.amountCents)}",
                fontWeight = FontWeight.Bold,
                color = if (isCredit) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.error,
                style = MaterialTheme.typography.bodyMedium
            )
        }
    )
    HorizontalDivider()
}
