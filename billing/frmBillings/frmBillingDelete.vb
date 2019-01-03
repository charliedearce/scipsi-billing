Public Class frmBillingDelete
    Protected Overrides ReadOnly Property CreateParams() As System.Windows.Forms.CreateParams
        Get
            Const DROPSHADOW = &H20000
            Dim cParam As CreateParams = MyBase.CreateParams
            cParam.ClassStyle = cParam.ClassStyle Or DROPSHADOW
            Return cParam
        End Get
    End Property
    Private Sub frmBillingDelete_KeyDown(sender As Object, e As KeyEventArgs) Handles Me.KeyDown
        If (e.KeyCode = Keys.Escape) Then
            Me.Close()
        ElseIf (e.KeyCode = Keys.Back) Then
            If txtItemid.Text = "" Then
                MsgBox("Please Select Item to delete, then press Enter", MsgBoxStyle.Information, "Action!")
            Else
                Dim result As Integer = MessageBox.Show("Are you sure you want to Delete this item?", "Caution!", MessageBoxButtons.YesNo)

                If result = DialogResult.Yes Then
                    ItemDelete()
                    vItemDelete()
                    viewItem()
                End If

            End If
        End If
    End Sub

    Private Sub frmBillingDelete_Load(sender As Object, e As EventArgs) Handles MyBase.Load

        
        vItemDelete()
        Me.KeyPreview = True
    End Sub



    Private Sub dgDelete_KeyUp(sender As Object, e As KeyEventArgs)
        'On Error Resume Next
        'txtItemid.Text = dgDelete.CurrentRow.Cells(0).Value.ToString
    End Sub

    Private Sub dgDelete_CellContentClick(sender As Object, e As DataGridViewCellEventArgs)

    End Sub

 

    Private Sub dgDelete_CellContentClick_1(sender As Object, e As DataGridViewCellEventArgs) Handles dgDelete.CellContentClick

    End Sub

    Private Sub dgDelete_SelectionChanged(sender As Object, e As EventArgs) Handles dgDelete.SelectionChanged
        On Error Resume Next
        txtItemid.Text = dgDelete.CurrentRow.Cells(0).Value.ToString
    End Sub
End Class