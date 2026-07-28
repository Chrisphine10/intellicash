package ke.co.intellicash.android.ui.groups

import androidx.compose.foundation.clickable
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
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import dagger.hilt.android.lifecycle.HiltViewModel
import ke.co.intellicash.android.data.model.Group
import ke.co.intellicash.android.data.repository.ApiResult
import ke.co.intellicash.android.data.repository.IntelliCashRepository
import ke.co.intellicash.android.ui.components.ErrorScreen
import ke.co.intellicash.android.ui.components.LoadingScreen
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import javax.inject.Inject

sealed class GroupsUiState {
    data object Loading : GroupsUiState()
    data class Loaded(val groups: List<Group>) : GroupsUiState()
    data class Error(val message: String) : GroupsUiState()
}

@HiltViewModel
class GroupsViewModel @Inject constructor(
    private val repository: IntelliCashRepository
) : ViewModel() {
    private val _state = MutableStateFlow<GroupsUiState>(GroupsUiState.Loading)
    val state = _state.asStateFlow()

    init { load() }

    fun load() {
        viewModelScope.launch {
            _state.value = GroupsUiState.Loading
            when (val result = repository.getGroups()) {
                is ApiResult.Success -> _state.value = GroupsUiState.Loaded(result.data)
                is ApiResult.Error -> _state.value = GroupsUiState.Error(result.message)
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun GroupsScreen(
    onBack: () -> Unit,
    onGroupClick: (String) -> Unit,
    viewModel: GroupsViewModel = hiltViewModel()
) {
    val state by viewModel.state.collectAsState()

    Scaffold(
        topBar = {
            TopAppBar(
                title = { Text("Groups") },
                navigationIcon = {
                    IconButton(onClick = onBack) {
                        Icon(Icons.AutoMirrored.Filled.ArrowBack, contentDescription = "Back")
                    }
                }
            )
        }
    ) { padding ->
        when (val s = state) {
            is GroupsUiState.Loading -> LoadingScreen(modifier = Modifier.padding(padding))
            is GroupsUiState.Error -> ErrorScreen(s.message, viewModel::load, Modifier.padding(padding))
            is GroupsUiState.Loaded -> {
                if (s.groups.isEmpty()) {
                    Box(modifier = Modifier.padding(padding).fillMaxSize().padding(24.dp)) {
                        Text("No groups found", style = MaterialTheme.typography.bodyLarge)
                    }
                } else {
                    LazyColumn(
                        modifier = Modifier.padding(padding),
                        contentPadding = PaddingValues(16.dp),
                        verticalArrangement = Arrangement.spacedBy(8.dp)
                    ) {
                        items(s.groups, key = { it.id }) { group ->
                            GroupCard(group = group, onClick = { onGroupClick(group.id) })
                        }
                    }
                }
            }
        }
    }
}

@Composable
private fun GroupCard(group: Group, onClick: () -> Unit) {
    ElevatedCard(modifier = Modifier.fillMaxWidth().clickable(onClick = onClick)) {
        ListItem(
            headlineContent = { Text(group.name, fontWeight = FontWeight.SemiBold) },
            supportingContent = {
                Column {
                    Text("Code: ${group.code} | ${group.county}")
                    Text(
                        "Phase: ${group.phase.replace("_", " ")} | Cycle ${group.cycleNumber}",
                        style = MaterialTheme.typography.bodySmall
                    )
                }
            },
            leadingContent = {
                Icon(
                    Icons.Default.Groups,
                    contentDescription = null,
                    tint = MaterialTheme.colorScheme.primary
                )
            },
            trailingContent = {
                Icon(Icons.Default.ChevronRight, contentDescription = null)
            }
        )
    }
}
